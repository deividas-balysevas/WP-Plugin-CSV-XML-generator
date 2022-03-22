<?php

namespace fishwish;

class csvgenerator {

    public function __construct() {

        add_action('init', array($this, 'wp_init'));
    }

    public function wp_init() {
        $last_update = get_option('csv_generated_at', 0);
        if ($last_update < time() - (3600 * 4) || isset($_GET['csv-generate'])){
            $this->generate();
            update_option('csv_generated_at', time());
        }
    }

    private function generate() {
        $args = [
            'status' => 'publish',
            'numberposts' => -1
        ];
        $wc_products = wc_get_products($args);
        if (count($wc_products)) {

            $manufacturer = get_bloginfo('name');

            /*
            ID	Item title	Final URL	Image URL	Item subtitle	Item description	Item Category	Price	Sale Price	Formatted price	Formatted sale price	Contextual keywords
                */
            $data = [];
            $data[] = [
                'ID',
                'Item title',
                'Final URL',
                'Image URL',
                //'Item subtitle',
                //'Item description',
                //'Item Category',
                'Price',
                'Sale Price',
                //'Formatted price',
                //'Formatted sale price',
                //'Contextual keywords'
            ];
            foreach ($wc_products as $wc_product) {


                if ($wc_product->get_type() == "variable") {
                    $variations = $wc_product->get_available_variations('object');
                    foreach ($variations as $variation) {

                        $data[] = [
                            $variation->get_id(),
                            $variation->get_title(),
                            get_permalink($variation->get_id()),
                            get_the_post_thumbnail_url($variation->get_id(), 'full') ?: get_the_post_thumbnail_url($wc_product->get_id(), 'full'),
                            //'',
                            //$this->removeNl($wc_product->get_description()),
                            //$this->prepair_cats($wc_product->get_id()),
                            $this->prepairPrice($variation->get_regular_price()),
                            $variation->get_sale_price()?$this->prepairPrice($variation->get_sale_price()):'',
                            //'',
                            //'',
                            //''
                        ];


                    }
                } else {


                    $data[] = [
                            $wc_product->get_id(),
                            $wc_product->get_title(),
                            get_permalink($wc_product->get_id()),
                            get_the_post_thumbnail_url($wc_product->get_id(), 'full'),
                            //'',
                            //$this->removeNl($wc_product->get_description()),
                            //$this->prepair_cats($wc_product->get_id()),
                            $this->prepairPrice($wc_product->get_regular_price()).' EUR',
                            $wc_product->get_sale_price()?$this->prepairPrice($wc_product->get_sale_price()).' EUR':'',
                            //'',
                            //'',
                            //''
                        ];


                }
            }
            $fp = fopen(ABSPATH . '/products.csv', 'w');
            fputs($fp, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
            foreach ($data as $fields) {
                fputcsv($fp, $fields, ',','"',"\n");
            }
            fclose($fp);
            /*
            $contents = file_get_contents(ABSPATH . '/products.csv');
            mb_convert_encoding($contents, 'UTF-16LE', 'UTF-8');
            file_put_contents(ABSPATH . '/products.csv', chr(255) . chr(254) . $contents);
            */
        }
    }

    private function removeNl($text){
        $text = str_replace(array("\r\n", "\n\r", "\n", "\r"), ' ', $text);
        return $text;
    }

    private function prepair_cats($id){
        $data = [];
        $terms = $this->sort_terms_hierarchicaly(get_the_terms( $id, 'product_cat' ));
        foreach($terms as $category) {
            $data[] = $category->name;
            if (is_array($category->children)){
                foreach($category->children as $child){
                    $data[] = $child->name;
                }
            }
        }
        return implode('/', $data);
    }

    private function sort_terms_hierarchicaly(Array $cats, $parentId = 0)
    {
        $into = [];
        foreach ($cats as $i => $cat) {
            if ($cat->parent == $parentId) {
                $cat->children = $this->sort_terms_hierarchicaly($cats, $cat->term_id);
                $into[$cat->term_id] = $cat;
            }
        }
        return $into;
    }

    private function prepairPrice($price){
        return number_format((float)$price, 2, '.', '');
    }

    private function prepairSpecs($product){
        $specs = [];
        foreach ($product->get_attributes() as $taxonomy => $terms_slug) {

            if (!is_array($terms_slug)) {
                $terms_slug = array($terms_slug);
            }

            foreach ($terms_slug as $term) {
                $term_obj = get_term_by('slug', $term, str_ireplace('attribute_', '', $taxonomy));
                if (is_object($term_obj)){
                    $specs[] = [$this->prepairLabel($taxonomy), $taxonomy, $term_obj->name];
                } else {
                    $specs[] = [$this->prepairLabel($taxonomy), $taxonomy, $term];
                }
            }
        }
        return $specs;
    }

    private function prepairLabel($label){
        $label = wc_attribute_label($label);
        return ucfirst(str_ireplace('-', ' ', $label));
    }

}
