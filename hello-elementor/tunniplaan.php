<?php


function tunniplaan_shortcode() {
    return '<p class="test">Tunniplaan</p>';
}

add_shortcode( 'tunniplaan', 'tunniplaan_shortcode' );