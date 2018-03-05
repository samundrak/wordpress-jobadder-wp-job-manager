<?php
require_once 'JobAdder.php';
if (!function_exists('wp_handle_upload')) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
}

function enable_extended_upload($mime_types = array())
{

    // The MIME types listed here will be allowed in the media library.

    // You can add as many MIME types as you want.

    $mime_types['gz'] = 'application/x-gzip';

    $mime_types['zip'] = 'application/zip';

    $mime_types['rtf'] = 'application/rtf';

    $mime_types['ppt'] = 'application/mspowerpoint';

    $mime_types['ps'] = 'application/postscript';

    $mime_types['flv'] = 'video/x-flv';

    $mime_types['xml'] = 'application/xml';

    // If you want to forbid specific file types which are otherwise allowed,

    // specify them here.  You can add as many as possible.

    unset($mime_types['exe']);

    unset($mime_types['bin']);

    return $mime_types;

}

add_filter('upload_mimes', 'enable_extended_upload');

function process_feed(WP_REST_Request $request)
{
    try {
            $jobAdder = new JobAdder;
            $jobAdder->parseXML($request->get_body());

            $jobAdder->setCompany((string)$jobAdder->xml->attributes()->advertiser);
            foreach ($jobAdder->xml as $job) {
                $jobAdder->createJob($job);
            }
        
        return 'Data saved successfully';
    } catch (Exception $e) {
        throw $e;
    }
}

add_action('rest_api_init', function () {
    register_rest_route('jobadder/', '/feed', array(
        'methods' => 'POST',
        'callback' => 'process_feed',
    ));
});
