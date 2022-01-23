<?php

namespace GreatScottPlugins\GreatScottRelevantImageSwap;

use GreatScottPlugins\WordPressPlugin\Plugin;

/**
 * Class RelevantImageSwap
 *
 * @package GreatScottPlugins\GreatScottRelevantImageSwap
 */
class RelevantImageSwap extends Plugin
{
    /**
     * Init method.
     */
    public function init()
    {
    }

    /**
     * Grab images from api.
     *
     * @action admin_notices
     */
    public function grabImages()
    {
        global $post;
        $postid = $post->ID;

        $post_data = str_replace('\\"', '"', $post->post_content); //parse_blocks() need " to parse $blocks[]['attr'] correctly
        $blocks = parse_blocks($post_data);

        if (false === empty($blocks)) {
            $do_it = false;

            foreach($blocks as $index => $block) {
                if ('core/image' === $block['blockName']) {
                    $image_html = $block['innerHTML'];
                    $dom        = new \DOMDocument('1.0', 'UTF-8');
                    @$dom->loadHTML($image_html);
                    $dom->preserveWhiteSpace = false;
                    $image                   = $dom->getElementsByTagName('img');
                    $image_alt               = $image[0]->getAttribute('alt');
                    $response                = wp_remote_get(
                        "https://api.pexels.com/v1/search?query={$image_alt}&per_page=1",
                        ['headers' => [
                            'Authorization' => 'Bearer 563492ad6f9170000100000152f75f6ac1dd42b58434e79f4295a102'
                    ]]
                    );
                    $photo_response          = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($photo_response['photos']) && is_array($photo_response)) {
                        $photo = $photo_response['photos'][0]['src']['large'];
                        $image[0]->setAttribute('src', $photo);
                        $final_image           = str_replace(
                            [
                                '<body>',
                                '</body>',
                                '<html>',
                                '</html>',
                                '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">'
                            ],
                            ['', '', '', '', ''],
                            $dom->saveHTML()
                        );
                        $block['innerHTML']    = $final_image;
                        $block['innerContent'] = [$final_image];
                        $blocks[$index]        = $block;
                        $do_it                 = true;
                    }
                }
            }

            $post_content = implode( '', array_map( [$this, 'serialize_block2'], $blocks ) ); //serialize_block() replacement because serialize_block_attributes() does not support JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            $post_content = str_replace('"', '\\"', $post_content); //undo " correction from above

            if ($do_it) {
                wp_update_post(
                    [
                        'ID'           => $postid,
                        'post_content' => $post_content
                    ],
                    false,
                    true
                );
            }
        }
    }

    //only sub method calls has been changed
    public function serialize_block2($block)
    {
        $block_content = '';

        $index = 0;
        foreach ( $block['innerContent'] as $chunk ) {
            $block_content .= is_string( $chunk ) ? $chunk : $this->serialize_block2( $block['innerBlocks'][ $index++ ] ); //change
        }

        if ( ! is_array( $block['attrs'] ) ) {
            $block['attrs'] = array();
        }

        return $this->get_comment_delimited_block_content2(
            $block['blockName'],
            $block['attrs'],
            $block_content
        );
    }

    //only sub method calls has been changed
    public function get_comment_delimited_block_content2( $block_name, $block_attributes, $block_content ) {
        if ( is_null( $block_name ) ) {
            return $block_content;
        }

        $serialized_block_name = strip_core_block_namespace( $block_name );
        $serialized_attributes = empty( $block_attributes ) ? '' : $this->serialize_block_attributes2( $block_attributes ) . ' '; //change

        if ( empty( $block_content ) ) {
            return sprintf( '<!-- wp:%s %s/-->', $serialized_block_name, $serialized_attributes );
        }

        return sprintf(
            '<!-- wp:%s %s-->%s<!-- /wp:%s -->',
            $serialized_block_name,
            $serialized_attributes,
            $block_content,
            $serialized_block_name
        );
    }


//change gutenberg json_encoding to keep plugin block formats and prevent issue "This block contains unexpected or invalid content."
    public function serialize_block_attributes2( $block_attributes ) {
        $encoded_attributes = json_encode( $block_attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); //change
        $encoded_attributes = preg_replace( '/--/', '\\u002d\\u002d', $encoded_attributes );
        $encoded_attributes = preg_replace( '/</', '\\u003c', $encoded_attributes );
        $encoded_attributes = preg_replace( '/>/', '\\u003e', $encoded_attributes );
        $encoded_attributes = preg_replace( '/&/', '\\u0026', $encoded_attributes );
        // Regex: /\\"/
        $encoded_attributes = preg_replace( '/\\\\"/', '\\u0022', $encoded_attributes );

        return $encoded_attributes;
    }
}