<?php
/**
 * Main class for image swap functionality.
 */

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
     * @filter content_save_pre
     *
     * @param string $content
     */
    public function grabImages(string $content): string
    {
        // parse_blocks() need " to parse $blocks[]['attr'] correctly.
        $post_data = str_replace('\\"', '"', $content);
        $blocks    = parse_blocks($post_data);

        // If no blocks exist ignore.
        if (false === empty($blocks)) {
            foreach ($blocks as $index => $block) {
                // Look for first level image blocks.
                if ('core/image' === $block['blockName']) {
                    $image_html = $block['innerHTML'];

                    // Convert image html to manipulate attributes.
                    $dom = new \DOMDocument('1.0', 'UTF-8');
                    @$dom->loadHTML($image_html);
                    $dom->preserveWhiteSpace = false;
                    $image                   = $dom->getElementsByTagName('img');

                    // If no image found go to next block.
                    if (false === isset($image[0])) {
                        continue;
                    }

                    // Get image alt or title for relevant query.
                    $image_alt = $image[0]->getAttribute('alt');
                    $image_alt = $image_alt ?? $image[0]->getAttribute('title');

                    if (empty($image_alt)) {
                        continue;
                    }

                    // Only grab a new images from api if not already from relevant image source.
                    if (false === stripos('pixabay', $image[0]->getAttribute('src'))) {
                        $image_alt      = str_word_count($image_alt) > 2 ? wp_trim_words($image_alt, 2) : $image_alt;
                        $image_alt      = str_replace([' ', '-'], ['+', '+'], $image_alt);
                        $response       = wp_remote_get(
                            "https://pixabay.com/api/?" .
                            "key=25377138-b2433b469a1712316da4ba2f5" .
                            "&q={$image_alt}"
                        );
                        $photo_response = json_decode(wp_remote_retrieve_body($response), true);

                        // If images exist for query replace current src with new license free version.
                        if (isset($photo_response['hits']) && is_array($photo_response)) {
                            $photo = $photo_response['hits'][0]['largeImageURL'] ?? '';

                            // Update image src if available.
                            if (false === empty($photo)) {
                                $image[0]->setAttribute('src', $photo);
                            }

                            // Remove extra tags from domdoc save.
                            $final_image = str_replace(
                                [
                                    '<body>',
                                    '</body>',
                                    '<html>',
                                    '</html>',
                                    '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">',
                                ],
                                ['', '', '', '', ''],
                                $dom->saveHTML()
                            );

                            // Refill the block HTML and inner Content with new image code.
                            $block['innerHTML']    = $final_image;
                            $block['innerContent'] = [$final_image];
                            $blocks[$index]        = $block;
                        }
                    }
                }
            }

            // Fix block code.
            $post_content = implode('', array_map([$this, 'serializeBlock2'], $blocks));
            $content      = str_replace('"', '\\"', $post_content); // Undo " correction from above.
        }

        return $content;
    }

    /**
     * serialize_block_attributes() does not support JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE.
     *
     * @param array $block The block info array.
     *
     * @return string
     */
    public function serializeBlock2(array $block): string
    {
        $block_content = '';

        $index = 0;
        foreach ($block['innerContent'] as $chunk) {
            $block_content .= is_string($chunk) ? $chunk : $this->serializeBlock2($block['innerBlocks'][$index++]); //change
        }

        if ( ! is_array($block['attrs'])) {
            $block['attrs'] = [];
        }

        return $this->getCommentDelimitedBlockContent2(
            $block['blockName'],
            $block['attrs'],
            $block_content
        );
    }

    /**
     * Only sub method calls has been changed.
     *
     * @param $block_name
     * @param array $block_attributes
     * @param string $block_content
     *
     * @return string
     */
    public function getCommentDelimitedBlockContent2(
        $block_name,
        array $block_attributes,
        string $block_content
    ): string {
        if (is_null($block_name)) {
            return $block_content;
        }

        $serialized_block_name = strip_core_block_namespace($block_name);
        $serialized_attributes = true === empty($block_attributes) ? '' : $this->serializeBlockAttributes2($block_attributes) . ' '; // Change.

        if (true === empty($block_content)) {
            return sprintf('<!-- wp:%s %s/-->', $serialized_block_name, $serialized_attributes);
        }

        return sprintf(
            '<!-- wp:%s %s-->%s<!-- /wp:%s -->',
            $serialized_block_name,
            $serialized_attributes,
            $block_content,
            $serialized_block_name
        );
    }

    /**
     * Change gutenberg json_encoding to keep plugin block formats and prevent issue
     * "This block contains unexpected or invalid content."
     *
     * @param array $block_attributes
     *
     * @return array|string|string[]|null
     */
    public function serializeBlockAttributes2(array $block_attributes)
    {
        $encoded_attributes = json_encode($block_attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); //change
        $encoded_attributes = preg_replace('/--/', '\\u002d\\u002d', $encoded_attributes);
        $encoded_attributes = preg_replace('/</', '\\u003c', $encoded_attributes);
        $encoded_attributes = preg_replace('/>/', '\\u003e', $encoded_attributes);
        $encoded_attributes = preg_replace('/&/', '\\u0026', $encoded_attributes);

        // Regex: /\\"/.
        return preg_replace('/\\\\"/', '\\u0022', $encoded_attributes);
    }
}