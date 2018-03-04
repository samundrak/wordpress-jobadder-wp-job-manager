<?php
require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');

class JobAdder
{

    private $company;
    private $companySite;

    public function parseXML($xmlData)
    {
        $this->xml = simplexml_load_string(
            $xmlData,
            'SimpleXMLElement',
            LIBXML_NOCDATA
        ) or die("Error: Cannot create object");
        return $this->xml;
    }

    public function toJSON()
    {
        $json = json_encode($this->xml);
        $array = json_decode($json, TRUE);
        return $array;
    }

    public function upsertCategory($categories)
    {

    }

    public function extractCategory($fields)
    {
        $category = [];
        foreach ($fields->Field as $field) {
            $fieldName = $field->attributes()['name'];
            if ($fieldName == 'Category') {
                foreach ($field->Values as $value) {
                    foreach ($value as $c) {
                        $categoryName = (string)$c->attributes()->name;
                        if (!array_key_exists($categoryName, $category)) {
                            $category[$categoryName] = [];
                        }
                        foreach ($c->Field->Values->Value as $subCat) {
                            $category[$categoryName][] = (string)$subCat->attributes()->name;
                        }
                    }
                }
            };
        }

        return $category;
    }

    public function createJob($job)
    {
        global $wpdb;
        // Create post object
        if (!$job->Title) return;
        $jobAdderId = (string)$job->attributes()->jid;
        $meta = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $wpdb->postmeta . " WHERE meta_key=%s AND meta_value=%s", 'jobadder_id', $jobAdderId));
        $postId = null;
        if (sizeof($meta) > 0) {
            $postId = $meta[0];
        }
        $classification = $this->classification($job->Classifications->Classification);
        $my_post = array(
            'post_title' => (string)$job->Title,
            'post_content' => $job->Summary
                . '<br/> '
                . $this->bulletPointsToList($job->BulletPoints)
                . '</br>'
                . $job->Description,
            'guid' => $jobAdderId,
            'post_author' => 1,
            'post_type' => 'job_listing',
            'post_name' => str_replace(' ', '-', $job->Title),
            'ping_status' => 'closed',
            'comment_status' => 'closed',
            'post_status' => 'publish',
            'meta_input' => array_merge($this->createMetaData($job, $classification), [
                'jobadder_id' => (int)$jobAdderId
            ])
        );

        if (!is_null($postId)) {
            $my_post['ID'] = $postId;
        }
        $workType = $this->parseTerms($classification['workType']);
        $postId = wp_insert_post($my_post);
        if (!is_null($postId)) {
            $terms = get_terms();
            foreach ($terms as $term) {
                if ($term->name == $workType) {
                    wp_set_object_terms($postId, $term->term_id, 'job_listing_type');
                }
            }
        }
        // Insert the post into the database
    }

    public function parseTerms($workType)
    {
        return trim(explode('/', $workType)[1]);
    }

    private function classification($classification)
    {
        return [
            'category' => ['parent' => $classification[0], 'sub' => $classification[1]],
            'location' => $classification[2],
            'workType' => $classification[3],
        ];
    }

    private function createMetaData($job, $classification)
    {
        return array(
            '_apply_link' => (string)$job->Apply->Url,
            '_jetpack_dont_email_post_to_subs' => '1',
            '_salary_min' => $job->Salary->MinValue,
            '_company_name' => $this->company,
            '_company_website' => '',
            '_company_tagline' => '',
            '_job_location' => (string)$classification['location'],
            '_job_expires' => '',
            // '_rate_max' => '60',
            '_salary_max' => $job->Salary->MaxValue,
            // '_rate_min' => '45',
            // '_hours' => '40',
            // '_wpb_vc_js_status' => 'false',
            // 'education_level' => 'bachelor-degree',
            // 'age_max' => '50',
            // 'age_min' => '25',
            // 'gender' => '0',
            // 'years_of_experience' => 'a:4:{i:0;s:1:"2";i:1;s:1:"3";i:2;s:1:"4";i:3;s:1:"5";}',
            // '_years_of_experience' => 'a:3:{i:0;s:1:"1";i:1;s:1:"2";i:2;s:1:"3";}',
            // '_education_level' => 'bachelor-degree',
            // '_age_max' => '45',
            // '_age_min' => '25',
            // '_gender' => 'female',
            // '_wxr_import_user_slug' => 'samundrak',
            // '_wxr_import_term' => 'a:3:{s:8:"taxonomy";s:15:"job_listing_tag";s:4:"slug";s:11:"engineering";s:4:"name";s:11:"Engineering";}',
            // '_wxr_import_term' => 'a:3:{s:8:"taxonomy";s:15:"job_listing_tag";s:4:"slug";s:11:"experienced";s:4:"name";s:11:"Experienced";}',
            // '_wxr_import_term' => 'a:3:{s:8:"taxonomy";s:15:"job_listing_tag";s:4:"slug";s:9:"full-time";s:4:"name";s:9:"Full Time";}',
            // '_wxr_import_term' => 'a:3:{s:8:"taxonomy";s:15:"job_listing_tag";s:4:"slug";s:13:"manufacturing";s:4:"name";s:13:"Manufacturing";}',
            // '_wxr_import_term' => 'a:3:{s:8:"taxonomy";s:15:"job_listing_tag";s:4:"slug";s:11:"non-manager";s:4:"name";s:11:"Non-Manager";}',
            // '_wxr_import_term' => 'a:3:{s:8:"taxonomy";s:15:"job_listing_tag";s:4:"slug";s:7:"seattle";s:4:"name";s:7:"Seattle";}'
        );
    }

    private function bulletPointsToList($bulletPoints)
    {
        $li = '';
        foreach ($bulletPoints as $points) {
            foreach ($points as $point) {
                $li .= '<li> ' . $point . '</li>';
            }
        }
        return '<ul>' . $li . '</ul>';
    }

    public function setCompany($company)
    {
        $this->company = $company;
    }

    /**
     * @param mixed $companySite
     */
    public function setCompanySite($companySite)
    {
        $this->companySite = $companySite;
    }
}




