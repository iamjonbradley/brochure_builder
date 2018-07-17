<?php
/**
 * Plugin Name: Brochure Builder
 * Plugin URI: http://thefloridadesigngroup.com
 * Version: 1.1.1
 * Description: Custom Brochure Builder for BionixMT
 * Author: The Florida Design Group
 * Author URI: http://thefloridadesigngroup.com
 */

$bbUploadDir = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'brochure_builder';
$bbUploadUrl = wp_upload_dir()['baseurl'] . DIRECTORY_SEPARATOR . 'brochure_builder';

if (!is_admin()) add_action("wp_enqueue_scripts", "bb_register_script", 11);
function bb_register_script() {
    wp_register_style('styles', plugins_url('/assets/css/styles.css', __FILE__), false, '1.0.0', 'all');
    wp_enqueue_style('styles');
}


function brochure_builder_endpoint() {

    $products = get_posts(array(
        'post_type' => 'product',
        'posts_per_page' => 100,
        'orderby' => 'title',
        'order' => 'ASC'
    ));

    $productList = '';
    foreach ($products as $product) {
        $productName = $product->post_title;
        $slug = $product->post_name;

        // sellsheet object
        $sellsheet = get_field('sellsheet', $product->ID);

        if (!empty($sellsheet)) {
            $productList .= '<option value="'. $slug .'">'. $productName .'</option>';
        }

        
    }
    return $productList;
}

function brochure_builder_form() {
    echo '<form action="" method="post" id="brochure_builder_form">';

    echo '<div class="form-group required">';
    echo '<label for="facilityName">Facility name</label>';
    echo '<input type="text" name="brochure_builder_facilityName" placeholder="Enter the name of the facility" required>';
    echo '</div>';

    echo '<div class="form-group required">';
    echo '<label for="yourName">Your name</label>';
    echo '<input type="text" name="brochure_builder_name" placeholder="Enter name" required>';
    echo '</div>';

    echo '<div class="form-group required">';
    echo '<label for="yourEmail">Your email</label>';
    echo '<input type="email" name="brochure_builder_email" placeholder="Enter email" required>';
    echo '</div>';

    echo '<div class="form-group required">';
    echo '<label for="yourPhone">Your phone</label>';
    echo '<input type="phone" name="brochure_builder_phone" placeholder="Enter phone" required>';
    echo '</div>';

    echo '<div class="form-group required">';
    echo '<label for="products">Select which products you want</label>';
    echo '<em>*Hold CTRL to select multiple products</em>';
    echo '<select multiple name="brochure_builder_products[]" required id="brochure_builder_products">';
    echo brochure_builder_endpoint();
    echo '</select>';
    echo '</div>';

    echo '<input type="hidden" name="brochure_builder_submitted" value="1">';

    echo '<button type="submit" class="btn btn-primary">Submit</button>';
    echo '</form>';
}

function brochure_builder() {
    global $bbUploadDir;
    ob_start();

    if (!file_exists($bbUploadDir)) {
        mkdir($bbUploadDir);
    }

    $filename = MD5(microtime());
    bochure_builder_process($filename);
    brochure_builder_form();

    return ob_get_clean();
}
add_shortcode('brochure_builder', 'brochure_builder');

function bochure_builder_process($filename) {
    global $bbUploadDir;
    global $bbUploadUrl;

    if (isset($_POST['brochure_builder_submitted'])) {

        require_once('libraries/PDFMerger.php');
        require_once('libraries/tcpdf/tcpdf.php');
        require_once('libraries/MyPDF.php');

        $base       = dirname(__FILE__);

        $coverBlank     = $assets . 'cover_page.jpg';
        $coverPageImage = $bbUploadDir .'/'. $filename . '-image.jpg';
        $coverPagePDF   = $bbUploadDir .'/'. $filename . '-cover_page.pdf';
        $brochure       = $bbUploadDir .'/'. $filename . '-brochure.pdf';

        // generate coverpage
        brochure_builder_cover_image($filename);

        // merge all the PDFS
        $pdf = new PDFMerger();

        $pdf->addPDF($coverPagePDF, 'all');

        foreach ($_POST['brochure_builder_products'] as $product) {
            brochure_builder_add_sellsheet($pdf, $product);
        }


        $pdf->merge('file', $brochure); // generate the file

        echo '<div class="bb_alert bb_alert-success">';
        echo '<a href="'. $bbUploadUrl .'/' . $filename .'-brochure.pdf" target="new">Click Here to Download Your Brochure</a>';
        echo '</div>';

        @unlink($coverPageImage);
        @unlink($coverPagePDF);
    }
}

function brochure_builder_cover_image($filename) {
    global $bbUploadDir;

    $base       = dirname(__FILE__);
    $output     = $base . '/output/';
    $assets     = $base . '/assets/';

    $coverBlank     = dirname(__FILE__) . '/assets/cover_page.jpg';
    $coverPageImage = $bbUploadDir .'/'. $filename . '-image.jpg';
    $coverPagePDF   = $bbUploadDir .'/'. $filename . '-cover_page.pdf';

    // Create Image From Existing File
    $jpg_image = imagecreatefromjpeg($coverBlank);

    // Allocate A Color For The Text
    $white = imagecolorallocate($jpg_image, 255, 255, 255);

    bochure_builder_writeText($jpg_image, 35, 184, 1340, 'white', 'arialblack', sanitize_text_field($_POST['brochure_builder_facilityName']));
    bochure_builder_writeText($jpg_image, 25, 184, 1490, 'white', 'arial', sanitize_text_field($_POST['brochure_builder_name']));
    bochure_builder_writeText($jpg_image, 25, 184, 1540, 'white', 'arial', 'Phone: ' . sanitize_text_field($_POST['brochure_builder_phone']));
    bochure_builder_writeText($jpg_image, 25, 184, 1590, 'white', 'arial', 'Email: ' . sanitize_text_field($_POST['brochure_builder_email']));

    // Print Text On Image
    imagejpeg($jpg_image, $coverPageImage);

    // Clear Memory
    imagedestroy($jpg_image);

    // create new PDF document
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // set document information
    $pdf->SetCreator(PDF_CREATOR);

    // set header and footer fonts
    $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));

    // set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);

    // remove default footer
    $pdf->setPrintFooter(false);

    // set auto page breaks
    $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

    // set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // set some language-dependent strings (optional)
    if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
        require_once(dirname(__FILE__).'/lang/eng.php');
        $pdf->setLanguageArray($l);
    }

    // ---------------------------------------------------------

    // add a page
    $pdf->AddPage();

    // get the current page break margin
    $bMargin = $pdf->getBreakMargin();
    // get current auto-page-break mode
    $auto_page_break = $pdf->getAutoPageBreak();
    // disable auto-page-break
    $pdf->SetAutoPageBreak(false, 0);
    // set bacground image
    $pdf->Image($coverPageImage, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
    // restore auto-page-break status
    $pdf->SetAutoPageBreak($auto_page_break, $bMargin);
    // set the starting point for the page con

    // ---------------------------------------------------------

    //Close and output PDF document
    $pdf->Output($coverPagePDF, 'F');

}


function bochure_builder_writeText($jpg_image, $size, $x, $y, $colorName, $font, $text)
{
    switch ($colorName) {
        case 'white':
            $color = imagecolorallocate($jpg_image, 255, 255, 255);
            break;
    }
    imagettftext($jpg_image, $size, 0, $x, $y, $color, dirname(__FILE__) . '/assets/fonts/' . $font . '.ttf', $text);
}

function brochure_builder_add_sellsheet($pdf, $product) {
    global $bbUploadDir;

    // get the current product
    $currentProduct = get_posts(array(
        'name'        => $product,
        'post_type'   => 'product',
        'post_status' => 'publish',
        'numberposts' => 1
    ));

    // sellsheet object
    $sellsheet_url = get_field('sellsheet', $currentProduct[0]->ID)['url'];

    // Get the uploads base URL and strip the scheme out
    $uploads_url = wp_upload_dir()['baseurl'];
    $uploads_url_path = preg_replace('/^https?:\/\//', '', $uploads_url);

    // Strip the scheme from the sellsheet URL
    $sellsheet_url_path = preg_replace('/^https?:\/\//', '', $sellsheet_url);

    // Remove and generate the full path
    // This will still break if someone were to use an outside URL, but there's not much we can do about that going this route
    $sellsheet_uploads_path = str_replace($uploads_url_path, '', $sellsheet_url_path); // This path is relative to the uploads directory
    $sellsheet_full_path = trailingslashit(wp_upload_dir()['basedir' ]) . stripslashes( $sellsheet_uploads_path); //

    // add the sellsheet to the brochure
    $pdf->addPDF($sellsheet_full_path, 'all');
}
