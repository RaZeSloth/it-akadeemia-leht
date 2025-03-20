<?php
function võistlused_shortcode() {

    $args = [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'tax_query'      => [
            [
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => 'voistlused',
            ],
        ],
    ];

    $posts = get_posts($args);

    $post_ids = [];

    $output = '<div class="voistlused_page">';
        $output .= '<div class="võistlused">';
            $output .= '<hr class="võistlusedHR">';

                if (!empty($posts)) {
                    foreach ($posts as $post) {
                        setup_postdata($post);

                        $Date = '';
                        $Loc = '';

                        $tags = get_the_tags($post->ID, 'post_tag');

                        foreach ($tags as $tag) {
                            if (substr($tag->name, 0, 4) === 'Date') {
                                $Date = substr($tag->name, 5);
                            } else {
                                $Loc = substr($tag->name, 4);

                            }
                        }

                        $post_ids[] .= $post->ID;
                        $counted_ids = count($post_ids);

                        $output .= '<div class="võistlus" style="background-color: ' . ($counted_ids % 2 === 0 ? '#EAECF0' : '#FFF') . ';">
                        <h2 style="font-size: 20px;"><a href="' . get_permalink($post) . '">' . get_the_title($post) . '</a></h2>';
                        $output .= '<h2 style="font-size: 15px; margin-left: 3%;">'. get_the_content($post) .'</h2>';
                        $output .= '<div class="võistlusLocDat">';
                        $output .= '<h2>'. $Date .'</h2>';
                        $output .= '<h2>'. $Loc .'</h2>';
                    $output .= '</div>';

                        $output .= '</div>';




                    }
                    wp_reset_postdata();
                } else {
                    echo "No posts found.";
                }

                $output .= '<hr class="võistlusedHR">';
            $output .= '</div>';
        $output .= '</div>';

    return $output;
}

add_shortcode('võistlused', 'võistlused_shortcode');