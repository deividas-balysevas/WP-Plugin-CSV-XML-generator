<?php

namespace kainoslt;

class xmlgenerator {

    public function __construct() {

        add_action('init', array($this, 'wp_init'));
    }

    public function wp_init() {
        $last_update = get_option('kainos_generated_at', 0);
        if ($last_update < time() - (3600 * 4) || isset($_GET['kainoslt-generate'])){
            $this->generate();
            update_option('kainos_generated_at', time());
        }
    }

    private function generate() {
        $args = [
            'status' => 'publish',
        ];
        $wc_products = wc_get_products($args);
        if (count($wc_products)) {

            $manufacturer = get_bloginfo('name');

            $dom = new \DOMDocument();
            $dom->encoding = 'utf-8';
            $dom->xmlVersion = '1.0';
            $dom->formatOutput = false;

            $products = $dom->createElement("products");
            $products = $dom->appendChild($products);

            foreach ($wc_products as $wc_product) {


                if ($wc_product->get_type() == "variable") {
                    $variations = $wc_product->get_available_variations('object');
                    foreach ($variations as $variation) {
                        $product = $dom->createElement('product');
                        $attr_id = new \DOMAttr('id', $variation->get_id());
                        $product->setAttributeNode($attr_id);
                        $products->appendChild($product);

                        $data = [
                            'title' => $variation->get_title(),
                            'item_price' => $this->prepairPrice($variation->get_price()),
                            'manufacturer' => $manufacturer,
                            'image_url' => get_the_post_thumbnail_url($variation->get_id(), 'full') ?: get_the_post_thumbnail_url($wc_product->get_id(), 'full'),
                            'product_url' => get_permalink($variation->get_id()),
                            'categories' => $this->prepair_cats($wc_product->get_id()),
                            'description' => $wc_product->get_description(),
                            'stock' => $variation->get_stock_quantity(),
                            'ean_code' => $variation->get_sku(),
                            'short_message' => $wc_product->get_short_description(),
                            'specs' => $this->prepairSpecs($variation),
                        ];

                        foreach ($data as $key => $title) {
                            if (is_array($title)){
                                if (count($title)){
                                    $specs = $dom->createElement('specs');
                                    $product->appendChild($specs);
                                    foreach ($title as $spec_data){
                                        $attr_name = new \DOMAttr('name', $spec_data[0]);
                                        $attr_value = new \DOMAttr('value', $spec_data[1]);
                                        $spec = $dom->createElement('spec');
                                        $spec->setAttributeNode($attr_name);
                                        $spec->setAttributeNode($attr_value);
                                        $specs->appendChild($spec);
                                        $spec->appendChild($dom->createCDATASection($spec_data[2]));
                                    }
                                }
                                continue;
                            }
                            if (!$title && $title != 0) {
                                continue;
                            }
                            $attr = $dom->createElement($key);
                            $attr->appendChild($dom->createCDATASection($title));
                            $product->appendChild($attr);
                        }
                    }
                } else {

                    $product = $dom->createElement('product');
                    $attr_id = new \DOMAttr('id', $wc_product->get_id());
                    $product->setAttributeNode($attr_id);
                    $products->appendChild($product);

                    $data = [
                        'title' => $wc_product->get_title(),
                        'item_price' => $this->prepairPrice($wc_product->get_price()),
                        'manufacturer' => $manufacturer,
                        'image_url' => get_the_post_thumbnail_url($wc_product->get_id(), 'full'),
                        'product_url' => get_permalink($wc_product->get_id()),
                        'categories' => $this->prepair_cats($wc_product->get_id()),
                        'description' => $wc_product->get_description(),
                        'stock' => $wc_product->get_stock_quantity(),
                        'ean_code' => $wc_product->get_sku(),
                        'short_message' => $wc_product->get_short_description(),
                        'specs' => $this->prepairSpecs($wc_product),
                    ];

                    foreach ($data as $key => $title) {
                        if (is_array($title)){
                            if (count($title)){

                            }
                            continue;
                        }
                        if (!$title) {
                            continue;
                        }
                        $attr = $dom->createElement($key);
                        $attr->appendChild($dom->createCDATASection($title));
                        $product->appendChild($attr);
                    }
                }
            }
            $dom->save(ABSPATH . '/kainoslt-list.xml');
            unset($dom);
        }
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
        return number_format($price, 2, '.', '');
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
