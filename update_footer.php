<?php
// Standalone script to update footer in database directly
if (file_exists(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
} else {
    die("Error: config.php not found.");
}

$mysqli = @new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($mysqli->connect_error) {
    die("Database Connection Error: " . $mysqli->connect_error);
}

$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'oc_';

echo "<h2>Updating Footer in Database...</h2>";

// 1. Update copyright in soconfig
$res = $mysqli->query("SELECT * FROM {$prefix}soconfig WHERE store_id = 0 AND `key` = 'soconfig_general_store'");
if ($row = $res->fetch_assoc()) {
    $v = json_decode($row['value'], true);
    $v['copyright'] = 'Copyright © {year}, Magical Singing Bowls, All Rights Reserved.';
    $new_json = $mysqli->real_escape_string(json_encode($v, JSON_UNESCAPED_SLASHES));
    $mysqli->query("UPDATE {$prefix}soconfig SET `value` = '{$new_json}' WHERE store_id = 0 AND `key` = 'soconfig_general_store'");
    echo "<p style='color: green;'>✔ 1. Copyright in {$prefix}soconfig updated successfully.</p>";
}

// 2. Build cleaned Page Builder structure for Module 45 (Footer 2)
$html_logo = '<div class="footer-logo"><a href="index.php?route=common/home" class="brand-link">Magical Singing Bowls</a></div>';

$html_links = '<ul class="footer-links"><li><a href="index.php?route=information/information&information_id=4">About Us</a></li><li><a href="index.php?route=information/information&information_id=6">Delivery Info</a></li><li><a href="index.php?route=account/returns|add">Returns</a></li><li><a href="index.php?route=information/information&information_id=3">Privacy Policy</a></li><li><a href="index.php?route=information/contact">Contact Us</a></li></ul>';

$html_social = '<div class="youtube"><ul class="socials">'
    . '<li class="facebook"><a href="https://www.facebook.com/CosmicSoundHealingAcademy" target="_blank" title="Facebook"><i class="fab fa-facebook-f fa-facebook"></i></a></li>'
    . '<li class="youtube"><a href="https://www.youtube.com/c/GovindaDhrubaTiwariSound-healing-master/" target="_blank" title="YouTube"><i class="fab fa-youtube"></i></a></li>'
    . '<li class="instagram"><a href="https://www.instagram.com/govinda.tiwari1/" target="_blank" title="Instagram"><i class="fab fa-instagram"></i></a></li>'
    . '</ul></div>';

$html_contact = '<div class="infos-footer box-footer"><div class="module"><h3 class="modtitle">CONTACT INFO</h3><ul class="menu"><li class="adres"><i class="fa fa-map-marker-alt fa-map-marker"></i><a href="index.php?route=information/information&information_id=4">Magical Creation Enterprises Pvt. Ltd.</a></li><li class="phone"><i class="fa fa-phone-alt fa-phone"></i><span>Hot Lines: (Also Social Medias)<br/><a href="tel:+9779851051290" class="contact-link">+977 9851051290</a></span></li><li class="mail"><i class="fa fa-envelope"></i><a href="mailto:sales.magicalsb@gmail.com" class="contact-link">sales.magicalsb@gmail.com</a></li></ul></div></div>';

$html_cust = '<div class="box-account box-footer"><div class="module clearfix"><h3 class="modtitle">CUSTOMER SERVICE</h3><div class="modcontent"><ul class="menu"><li><a href="index.php?route=information/contact">Contact Us</a></li><li><a href="index.php?route=account/returns|add">Returns</a></li><li><a href="index.php?route=information/sitemap">Site Map</a></li><li><a href="index.php?route=account/wishlist">Wish List</a></li><li><a href="index.php?route=account/order">Order History</a></li><li><a href="index.php?route=account/order">Track Your Order</a></li></ul></div></div></div>';

$html_info = '<div class="box-information box-footer"><div class="module clearfix"><h3 class="modtitle">IMPORTANT INFORMATION</h3><div class="modcontent"><ul class="menu"><li><a href="index.php?route=information/information&information_id=4">About Us</a></li><li><a href="index.php?route=information/information&information_id=3">Privacy Policy</a></li><li><a href="index.php?route=information/information&information_id=6">Packaging And Delivery Info</a></li><li><a href="index.php?route=information/information&information_id=5">Terms &amp; Conditions</a></li><li><a href="index.php?route=information/information&information_id=7">Return Policy</a></li><li><a href="index.php?route=information/information&information_id=14">Payment</a></li></ul></div></div></div>';

$html_serv = '<div class="box-service box-footer"><div class="module clearfix"><h3 class="modtitle">COURSES &amp; HEALING</h3><div class="modcontent"><ul class="menu"><li><a href="https://www.facebook.com/CosmicSoundHealingAcademy" target="_blank">Cosmic Sound Healing Academy</a></li><li><a href="https://www.magicalsingingbowls.com/Tibetan-singing-bowls-course" target="_blank">Singing Bowls Courses</a></li><li><a href="https://www.youtube.com/c/GovindaDhrubaTiwariSound-healing-master/" target="_blank">Sound Healing Master Lessons</a></li><li><a href="https://www.magicalsingingbowls.com/Sound-Therapy-planetary-tibetan-singing-bowls-healing-information" target="_blank">Sound Therapy Information</a></li><li><a href="index.php?route=cms/blog">Blog &amp; Articles</a></li></ul></div></div></div>';

$html_news = '<div class="module newsletter-footer1"><div class="newsletter" style="width:100%;"><div class="title-block"><div class="page-heading font-title">Signup For Newsletter</div><div class="promotext">We\'ll never share your email address with a third-party.</div></div><div class="block_content"><form method="post" id="signup-footer2" class="form-group form-inline signup send-mail" onsubmit="return subscribe_newsletter_footer();"><div class="form-group"><div class="input-box"><input type="email" placeholder="Your email address..." class="form-control" id="txtemail-footer" name="txtemail" autocomplete="off" required></div><div class="subcribe"><button class="btn btn-primary btn-default" type="submit" name="submit">Subscribe</button></div></div></form></div></div></div>';

function make_widget($name, $mod, $title, $html) {
    return [
        "name" => "Html",
        "module" => $mod,
        "type" => "shortcode",
        "shortcode" => "html",
        "content" => json_encode([
            "cparent" => [
                [
                    "name_shortcode_3" => $title,
                    "name_shortcode_1" => $title,
                    "name_shortcode_status" => "no",
                    "content_3" => $html,
                    "content_1" => $html,
                    "yt_class" => "",
                    "css_internal" => ""
                ]
            ],
            "cchild" => new stdClass()
        ], JSON_UNESCAPED_SLASHES)
    ];
}

$pb_data = [
    [
        "text_class_id" => "row_cd6l",
        "text_class" => "footer-top",
        "row_container_fluid" => "0",
        "cols" => [
            [
                "text_class_id" => "col_34s0",
                "text_class" => "col-12",
                "lg_col" => 12,
                "md_col" => 12,
                "sm_col" => 12,
                "xs_col" => 12,
                "rows" => [
                    [
                        "text_class_id" => "row_gl1p",
                        "text_class" => "row-style",
                        "cols" => [
                            [
                                "text_class_id" => "col_d9cx",
                                "text_class" => "col-style",
                                "lg_col" => 3,
                                "md_col" => 3,
                                "sm_col" => 12,
                                "xs_col" => 12,
                                "widgets" => [ make_widget("Html", "html_logo1", "Logo footer", $html_logo) ]
                            ],
                            [
                                "text_class_id" => "col_ok71",
                                "text_class" => "col-style hidden-xs",
                                "lg_col" => 6,
                                "md_col" => 5,
                                "sm_col" => 12,
                                "xs_col" => 12,
                                "widgets" => [ make_widget("Html", "html_links1", "Footer links", $html_links) ]
                            ],
                            [
                                "text_class_id" => "col_d2ob",
                                "text_class" => "col-style",
                                "lg_col" => 3,
                                "md_col" => 4,
                                "sm_col" => 12,
                                "xs_col" => 12,
                                "widgets" => [ make_widget("Html", "html_social1", "Social footer", $html_social) ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    [
        "text_class_id" => "row_p2b9",
        "text_class" => "footer-middle",
        "row_container_fluid" => "0",
        "cols" => [
            [
                "text_class_id" => "col_f2b9",
                "text_class" => "col-12",
                "lg_col" => 12,
                "md_col" => 12,
                "sm_col" => 12,
                "xs_col" => 12,
                "rows" => [
                    [
                        "text_class_id" => "row_c2c8",
                        "text_class" => "row-style",
                        "cols" => [
                            [
                                "text_class_id" => "col_4202",
                                "text_class" => "col-style",
                                "lg_col" => 4,
                                "md_col" => 6,
                                "sm_col" => 6,
                                "xs_col" => 12,
                                "widgets" => [ make_widget("Html", "html_contact1", "Contact Info", $html_contact) ]
                            ],
                            [
                                "text_class_id" => "col_bko7",
                                "text_class" => "col-style",
                                "lg_col" => 2,
                                "md_col" => 6,
                                "sm_col" => 6,
                                "xs_col" => 12,
                                "widgets" => [ make_widget("Html", "html_cust1", "Customer Service", $html_cust) ]
                            ],
                            [
                                "text_class_id" => "col_6urb",
                                "text_class" => "col-style",
                                "lg_col" => 3,
                                "md_col" => 6,
                                "sm_col" => 6,
                                "xs_col" => 12,
                                "widgets" => [ make_widget("Html", "html_info1", "Important Information", $html_info) ]
                            ],
                            [
                                "text_class_id" => "col_3d8g",
                                "text_class" => "col-style",
                                "lg_col" => 3,
                                "md_col" => 6,
                                "sm_col" => 6,
                                "xs_col" => 12,
                                "widgets" => [ make_widget("Html", "html_serv1", "Courses and Healing", $html_serv) ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    [
        "text_class_id" => "row_news",
        "text_class" => "footer-newsletter",
        "row_container_fluid" => "0",
        "cols" => [
            [
                "text_class_id" => "col_news",
                "text_class" => "col-12",
                "lg_col" => 12,
                "md_col" => 12,
                "sm_col" => 12,
                "xs_col" => 12,
                "widgets" => [ make_widget("Html", "html_news1", "Newsletter", $html_news) ]
            ]
        ]
    ]
];

$setting_obj = [
    "name" => "Footer 2",
    "status" => "1",
    "import_theme" => "2",
    "moduleid" => "45",
    "page_builder" => json_encode($pb_data, JSON_UNESCAPED_SLASHES)
];

$escaped_setting = $mysqli->real_escape_string(json_encode($setting_obj, JSON_UNESCAPED_SLASHES));
$mysqli->query("UPDATE {$prefix}module SET setting = '{$escaped_setting}' WHERE module_id = 45");

echo "<p style='color: green;'>✔ 2. Module 45 (Footer 2) updated successfully in {$prefix}module.</p>";
echo "<h3 style='color: green;'>All footer updates applied! You can delete update_footer.php now.</h3>";
