<?php
/**
 * 求人情報詳細ページテンプレート
 * Template Name: 求人情報詳細
 * 
 * このテンプレートは求人情報の詳細ページを表示するためのものです。
 * WordPressのカスタム投稿タイプ「job」と連携し、カスタムフィールドやタクソノミーから取得したデータを整形して表示します。
 */

// Cocoon テーマの不要なウィジェットを削除する
// 1. サイドバーから特定のウィジェットを削除（IDベース）
add_filter('sidebars_widgets', 'remove_specific_cocoon_widgets');
function remove_specific_cocoon_widgets($sidebars_widgets) {
    // 求人投稿タイプのシングルページでのみ適用（または常に適用）
    if (!is_singular('job')) {
        return $sidebars_widgets;
    }
    
    // 削除するウィジェットIDのパターン
    $patterns_to_remove = array(
        'popular_entries',  // 人気記事
        'new_entries',      // 新着記事
        'categories',       // カテゴリー
        'recent-posts',     // 新着記事（別の形式）
        'archives',         // アーカイブ
        'recent-comments'   // 最近のコメント
    );
    
    // すべてのサイドバーを処理
    foreach ($sidebars_widgets as $sidebar_id => $widgets) {
        if (is_array($widgets)) {
            foreach ($widgets as $key => $widget_id) {
                // パターンにマッチするウィジェットIDを削除
                foreach ($patterns_to_remove as $pattern) {
                    if (strpos($widget_id, $pattern) !== false) {
                        unset($sidebars_widgets[$sidebar_id][$key]);
                        break;
                    }
                }
            }
        }
    }
    
    return $sidebars_widgets;
}

// 2. Cocoon テーマのウィジェット表示をフィルタリング
add_filter('widget_display_callback', 'filter_cocoon_widgets', 10, 3);
function filter_cocoon_widgets($instance, $widget, $args) {
    // 求人投稿タイプのシングルページでのみ適用
    if (!is_singular('job')) {
        return $instance;
    }
    
    // ウィジェットのクラス名またはIDを取得
    $widget_class = get_class($widget);
    $widget_id = $widget->id;
    
    // 特定のウィジェットを非表示にする条件
    if (
        // クラス名で判定
        strpos($widget_class, 'Popular_Entries') !== false ||
        strpos($widget_class, 'New_Entries') !== false ||
        strpos($widget_class, 'Categories') !== false ||
        strpos($widget_class, 'Recent') !== false ||
        strpos($widget_class, 'Archives') !== false ||
        
        // IDで判定
        strpos($widget_id, 'popular_entries') !== false ||
        strpos($widget_id, 'new_entries') !== false ||
        strpos($widget_id, 'categories') !== false ||
        strpos($widget_id, 'recent-posts') !== false ||
        strpos($widget_id, 'archives') !== false ||
        
        // タイトルで判定（設定があれば）
        (isset($instance['title']) && (
            strpos($instance['title'], '人気') !== false ||
            strpos($instance['title'], '新着') !== false ||
            strpos($instance['title'], 'カテゴリー') !== false ||
            strpos($instance['title'], 'アーカイブ') !== false ||
            strpos($instance['title'], '最近のコメント') !== false
        ))
    ) {
        return false; // ウィジェットを表示しない
    }
    
    return $instance; // その他のウィジェットは表示する
}

// 3. Cocoon テーマ用のCSS対策を追加
add_action('wp_head', 'hide_cocoon_widgets_css', 999);
function hide_cocoon_widgets_css() {
    // 求人投稿タイプのシングルページでのみ適用
    if (!is_singular('job')) {
        return;
    }
    ?>
    <style>
    /* Cocoonテーマの人気記事・新着記事ウィジェットを非表示 */
    .widget_popular_entries,
    .widget_new_entries,
    #popular_entries-2,
    #new_entries-2,
    #categories-2,
    #archives-2,
    #recent-posts-2,
    #recent-comments-2,
    .widget-sidebar[id*="popular_entries"],
    .widget-sidebar[id*="new_entries"],
    .widget-sidebar[id*="categories"],
    .widget-sidebar[id*="recent-posts"],
    .widget-sidebar[id*="archives"],
    .widget-sidebar[id*="recent-comments"] {
        display: none !important;
    }
    
    /* タイトルベースでの非表示（より確実な対策） */
    .widget-sidebar .widget-title:contains("人気記事"),
    .widget-sidebar .widget-title:contains("新着記事"),
    .widget-sidebar .widget-title:contains("カテゴリー"),
    .widget-sidebar .widget-title:contains("アーカイブ"),
    .widget-sidebar .widget-title:contains("最近のコメント") {
        display: none !important;
    }
    
    /* 親要素全体を非表示（タイトルが一致する場合） */
    .widget-sidebar:has(.widget-title:contains("人気記事")),
    .widget-sidebar:has(.widget-title:contains("新着記事")),
    .widget-sidebar:has(.widget-title:contains("カテゴリー")),
    .widget-sidebar:has(.widget-title:contains("アーカイブ")),
    .widget-sidebar:has(.widget-title:contains("最近のコメント")) {
        display: none !important;
    }
    </style>
    <?php
}

// 4. 特定のウィジェットを登録解除（完全な削除）
add_action('widgets_init', 'unregister_specific_cocoon_widgets', 99);
function unregister_specific_cocoon_widgets() {
    // 常に実行するか、条件付きで実行
    // if (is_singular('job')) { // ※ページロード時点では is_singular が機能しないため注意
        // 特定のウィジェットを登録解除する試み
        // 注：クラス名は実際のテーマに合わせて調整する必要があるかもしれません
        if (class_exists('Popular_Entries_Widget')) {
            unregister_widget('Popular_Entries_Widget');
        }
        if (class_exists('New_Entries_Widget')) {
            unregister_widget('New_Entries_Widget');
        }
        if (class_exists('WP_Widget_Categories')) {
            unregister_widget('WP_Widget_Categories');
        }
        if (class_exists('WP_Widget_Recent_Posts')) {
            unregister_widget('WP_Widget_Recent_Posts');
        }
        if (class_exists('WP_Widget_Archives')) {
            unregister_widget('WP_Widget_Archives');
        }
        if (class_exists('WP_Widget_Recent_Comments')) {
            unregister_widget('WP_Widget_Recent_Comments');
        }
    // }
}

// 5. Cocoon特有のフィルターがあれば追加
add_filter('pre_get_posts', 'modify_sidebar_for_job_posts');
function modify_sidebar_for_job_posts($query) {
    // メインクエリの場合のみ
    if (!is_admin() && $query->is_main_query() && $query->is_singular('job')) {
        // Cocoonテーマ特有の変数やフィルターがあれば設定
        // 例: グローバル変数を設定
        global $g_sidebar_widget_mode;
        if (isset($g_sidebar_widget_mode)) {
            $g_sidebar_widget_mode = 'no_display';
        }
    }
    return $query;
}

// 以下は元の求人詳細ページテンプレートのコード
// デフォルトのサイドバーウィジェットを非表示にする - より限定的な方法で実装
add_filter('sidebars_widgets', 'disable_specific_sidebar_widgets');
function disable_specific_sidebar_widgets($sidebars_widgets) {
    if (is_singular('job')) {
        if (isset($sidebars_widgets['sidebar'])) {
            foreach ($sidebars_widgets['sidebar'] as $key => $widget_id) {
                // 特定のウィジェットのみを削除
                if (strpos($widget_id, 'recent-posts') !== false ||
                    strpos($widget_id, 'categories') !== false ||
                    strpos($widget_id, 'popular-posts') !== false) {
                    unset($sidebars_widgets['sidebar'][$key]);
                }
            }
        }
    }
    return $sidebars_widgets;
}

// 特定のウィジェットを非表示にする
add_action('widgets_init', 'remove_specific_widgets', 99);
function remove_specific_widgets() {
    if (is_singular('job')) {
        // グローバルに削除するのではなく条件付きで表示/非表示を制御
        add_filter('widget_display_callback', 'filter_widget_display', 10, 3);
    }
}

// ウィジェットの表示/非表示を制御
function filter_widget_display($instance, $widget, $args) {
    // 特定のウィジェットのみ非表示にする
    if ($widget instanceof WP_Widget_Recent_Posts ||
        $widget instanceof WP_Widget_Categories ||
        (class_exists('WP_Widget_Popular_Posts') && $widget instanceof WP_Widget_Popular_Posts)) {
        return false; // false を返すとウィジェットは表示されない
    }
    return $instance; // 他のウィジェットは通常通り表示
}

get_header();


// JavaScriptとスタイルシートを読み込み
wp_enqueue_style('job-listing-style', get_template_directory_uri() . '/assets/css/job-listing.css', array(), '1.0.0');
wp_enqueue_script('job-listing-script', get_template_directory_uri() . '/assets/js/job-listing.js', array('jquery'), '1.0.0', true);

// 投稿データ
$post_id = get_the_ID();
$job_title = get_the_title();
$job_content = get_the_content();
// タクソノミーデータ（名前で取得）
$job_location = wp_get_object_terms($post_id, 'job_location', array('fields' => 'names'));
// 職種の取得（カスタム関数を使用）
$job_position_display = get_job_position_display_text($post_id);
$job_position = !empty($job_position_display) ? array($job_position_display) : array();

// 雇用形態の取得（カスタム関数を使用）
$job_type_display = get_job_type_display_text($post_id);
$job_type = !empty($job_type_display) ? array($job_type_display) : array();
$facility_type = wp_get_object_terms($post_id, 'facility_type', array('fields' => 'names'));
$job_feature = wp_get_object_terms($post_id, 'job_feature', array('fields' => 'names'));
// タクソノミーIDも取得（関連求人用）
$job_location_ids = wp_get_object_terms($post_id, 'job_location', array('fields' => 'ids'));
$job_position_ids = wp_get_object_terms($post_id, 'job_position', array('fields' => 'ids'));
// カスタムフィールドデータ
$job_content_title = get_post_meta($post_id, 'job_content_title', true);
$salary_range = get_post_meta($post_id, 'salary_range', true);
$working_hours = get_post_meta($post_id, 'working_hours', true);
$holidays = get_post_meta($post_id, 'holidays', true);
$benefits = get_post_meta($post_id, 'benefits', true);
$requirements = get_post_meta($post_id, 'requirements', true);
$application_process = get_post_meta($post_id, 'application_process', true);
$contact_info = get_post_meta($post_id, 'contact_info', true);
$bonus_raise = get_post_meta($post_id, 'bonus_raise', true);
$capacity = get_post_meta($post_id, 'capacity', true);
$staff_composition = get_post_meta($post_id, 'staff_composition', true);
$daily_schedule_items = get_post_meta($post_id, 'daily_schedule_items', true);

// スタッフの声データの取得
$staff_voice_items = array();

// 1. まず投稿者（求人作成者）のユーザーメタから共通情報を取得
$post_author_id = get_post_field('post_author', $post_id);
if (!empty($post_author_id)) {
    $common_staff_voice = get_user_meta($post_author_id, 'common_staff_voice_items', true);
    if (!empty($common_staff_voice) && is_array($common_staff_voice)) {
        $staff_voice_items = $common_staff_voice;
    }
}

// 2. 共通情報がない場合は、既存の個別投稿メタデータから取得（下位互換性）
if (empty($staff_voice_items)) {
    $individual_staff_voice = get_post_meta($post_id, 'staff_voice_items', true);
    if (!empty($individual_staff_voice) && is_array($individual_staff_voice)) {
        $staff_voice_items = $individual_staff_voice;
    }
}

// 施設情報
$facility_name = get_post_meta($post_id, 'facility_name', true);
$facility_address = get_post_meta($post_id, 'facility_address', true);
$facility_tel = get_post_meta($post_id, 'facility_tel', true);
$facility_hours = get_post_meta($post_id, 'facility_hours', true);
$facility_url = get_post_meta($post_id, 'facility_url', true);
$facility_company = get_post_meta($post_id, 'facility_company', true);
$facility_map = get_post_meta($post_id, 'facility_map', true);
$company_url = get_post_meta($post_id, 'company_url', true);
// サムネイル画像URL（複数画像対応）
$thumbnail_url = get_the_post_thumbnail_url($post_id, 'large');
$gallery_images = get_post_meta($post_id, 'gallery_images', true); // ギャラリー画像用のカスタムフィールド
if (!$thumbnail_url) {
    $thumbnail_url = get_template_directory_uri() . '/assets/images/no-image.jpg';
}
// 求人特徴タグ（画像内で表示されているオレンジ/ピンクのアイコン）
$job_tags = wp_get_object_terms($post_id, 'job_feature', array('fields' => 'all'));

// 施設タイプのアイコン表示用関数
function get_facility_type_icon($type) {
    switch ($type) {
        case '放課後等デイサービス':
            return '<img src="' . get_template_directory_uri() . '/assets/images/icon-houday.jpg" alt="放デイ" width="70" height="70">';
        case '児童発達支援':
            return '<img src="' . get_template_directory_uri() . '/assets/images/icon-jidou.jpg" alt="児発支援" width="70" height="70">';
        // 他の施設タイプも同様に追加
        default:
            return '';
    }
}

// サブタイトルの生成
$job_subtitle = $facility_name . 'の' . (!empty($job_position) ? $job_position[0] : '') . '(' . (!empty($job_type) ? $job_type[0] : '') . ')の求人情報';

// 各セクションを表示するかどうかのフラグ
$show_job_info = true; // 求人情報は常に表示
$show_workplace_info = !empty($daily_schedule_items) || (!empty($staff_voice_items) && is_array($staff_voice_items));
$show_facility_info = true; // 施設情報は常に表示（基本情報があるため）

// 情報目次を表示するかどうか（いずれかのセクションが表示される場合）
$show_info_tabs = $show_job_info || $show_workplace_info || $show_facility_info;
?>
<!--<head>
<?php
// ★★★ Google for Jobs 構造化データ準備 ここから ★★★
$post_id = get_the_ID();

// 構造化データに必要な基本情報を取得
$job_position_display_text_sd = '';
if (function_exists('get_job_position_display_text')) {
    $job_position_display_text_sd = get_job_position_display_text($post_id);
} else {
    $job_position_terms_for_sd = wp_get_object_terms($post_id, 'job_position', array('fields' => 'names'));
    if (!empty($job_position_terms_for_sd) && is_array($job_position_terms_for_sd)) {
        $job_position_display_text_sd = $job_position_terms_for_sd[0];
    }
}

$job_type_display_text_sd = '';
if (function_exists('get_job_type_display_text')) {
    $job_type_display_text_sd = get_job_type_display_text($post_id);
} else {
    $job_type_terms_for_sd = wp_get_object_terms($post_id, 'job_type', array('fields' => 'names'));
    if (!empty($job_type_terms_for_sd) && is_array($job_type_terms_for_sd)) {
        $job_type_display_text_sd = $job_type_terms_for_sd[0];
    }
}

$thumbnail_url_sd = get_the_post_thumbnail_url($post_id, 'large');
if (!$thumbnail_url_sd) $thumbnail_url_sd = ''; // デフォルト画像パスは適宜設定


$structured_data_values = [];

// ヘルパー関数 (プレーンテキスト整形用 - responsibilities等で使用)
if (!function_exists('format_plain_text_for_schema_sd')) {
    function format_plain_text_for_schema_sd($content, $label = '') {
        if (empty(trim(is_string($content) ? strip_tags($content) : ''))) return '';
        $text_output = '';
        if (!empty($label)) $text_output .= trim(esc_html($label)) . ":\n";
        $cleaned_content = is_string($content) ? strip_tags($content) : '';
        $cleaned_content = str_replace(["\r\n", "\r", "<br />", "<br/>", "<br>"], "\n", $cleaned_content);
        $cleaned_content = html_entity_decode($cleaned_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleaned_content = preg_replace("/\n{3,}/", "\n\n", $cleaned_content);
        $cleaned_content = preg_replace("/[ \t]+(\n|$)/", "$1", $cleaned_content);
        $cleaned_content = preg_replace("/^[ \t]+/m", "", $cleaned_content);
        $cleaned_content = preg_replace('/[ \t]{2,}/', ' ', $cleaned_content);
        $cleaned_content = trim($cleaned_content);
        $text_output .= $cleaned_content;
        return trim($text_output);
    }
}

// ヘルパー関数 (HTML整形用 - description で使用)
if (!function_exists('format_html_content_for_schema_sd')) {
    function format_html_content_for_schema_sd($html_content, $allowed_tags = '<p><br><ul><ol><li><strong><em><b><i>') {
        if (empty(trim(strip_tags($html_content, $allowed_tags)))) {
            return '';
        }
        $cleaned_html = strip_tags($html_content, $allowed_tags);
        $cleaned_html = preg_replace('/(<br\s*\/?>\s*){3,}/i', '<br><br>', $cleaned_html);
        $cleaned_html = preg_replace('/<p>\s*(<br\s*\/?>)?\s*<\/p>/is', '', $cleaned_html);
        $cleaned_html = preg_replace('/<p><\/p>/is', '', $cleaned_html);
        return trim($cleaned_html);
    }
}
// ヘルパー関数: テキストをHTMLの段落として整形（ラベル付き）
if (!function_exists('format_text_as_html_paragraph_sd')) {
    function format_text_as_html_paragraph_sd($label, $text_content, $is_list = false) {
        if (empty(trim($text_content))) {
            return '';
        }
        $html = '';
        if (!empty($label)) {
            $html .= '<p><strong>' . esc_html($label) . '</strong></p>';
        }
        // 入力テキストをエスケープし、改行を<br>に変換
        $escaped_text = esc_html(trim($text_content));
        $content_html = nl2br($escaped_text);

        if ($is_list) { // 箇条書き風の場合、各行を<p>で囲むか、全体を一つの<p>に入れて<br>で区切る
            // ここでは、nl2brで既に<br>になっているので、全体を<p>で囲むだけで良い場合も
            // あるいは、入力が改行区切りのリストなら、各行を<li>として<ul>で囲むのが理想
            // 簡単のため、ここでは<p>で囲むか、そのまま<br>を活かす
             $html .= '<div>' . $content_html . '</div>'; // <div>で囲む方が汎用的かも
        } else {
            $html .= '<p>' . $content_html . '</p>';
        }
        return $html;
    }
}

// title (必須)
if (!empty($job_position_display_text_sd) && $job_position_display_text_sd !== 'その他') {
    $structured_data_values['title'] = $job_position_display_text_sd;
} else {
    $structured_data_values['title'] = get_the_title($post_id);
}

// datePosted (必須)
$structured_data_values['datePosted'] = get_the_date('Y-m-d', $post_id);

// hiringOrganization (必須)
$facility_name_for_hiring_org_sd = get_post_meta($post_id, 'facility_name', true);
$facility_url_sd = get_post_meta($post_id, 'facility_url', true);
$company_url_sd = get_post_meta($post_id, 'company_url', true);
$hiring_organization_sd = ['@type' => 'Organization'];
if (!empty($facility_name_for_hiring_org_sd)) $hiring_organization_sd['name'] = $facility_name_for_hiring_org_sd;
if (!empty($facility_url_sd) && filter_var($facility_url_sd, FILTER_VALIDATE_URL)) {
    $hiring_organization_sd['sameAs'] = $facility_url_sd;
} elseif (!empty($company_url_sd) && filter_var($company_url_sd, FILTER_VALIDATE_URL)) {
    $hiring_organization_sd['sameAs'] = $company_url_sd;
}
if (!empty($hiring_organization_sd['name'])) $structured_data_values['hiringOrganization'] = $hiring_organization_sd;

// jobLocation (必須) - 完全なロジック
$facility_address_sd = get_post_meta($post_id, 'facility_address', true);
$postal_code_sd = ''; $street_address_full_sd = ''; $address_locality_sd = ''; $address_region_sd = ''; $actual_street_address_for_schema_sd = '';
if (!empty($facility_address_sd)) {
    $address_parts_sd = preg_split('/(〒\s*\d{3}-\d{4})/', $facility_address_sd, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if (count($address_parts_sd) >= 1 && preg_match('/〒\s*(\d{3}-\d{4})/', $address_parts_sd[0], $postal_matches)) {
        $postal_code_sd = trim($postal_matches[1]);
        $street_address_full_sd = trim(isset($address_parts_sd[1]) ? $address_parts_sd[1] : preg_replace('/〒\s*\d{3}-\d{4}\s*/', '', $facility_address_sd, 1));
    } elseif (count($address_parts_sd) == 1 && !preg_match('/〒\s*\d{3}-\d{4}/', $address_parts_sd[0])) {
        $street_address_full_sd = trim($address_parts_sd[0]);
    } else { $street_address_full_sd = trim(preg_replace('/〒\s*\d{3}-\d{4}\s*/', '', $facility_address_sd));}
}
$job_location_term_ids_sd = wp_get_object_terms($post_id, 'job_location', array('fields' => 'ids'));
$most_specific_location_term_sd = null;
if (!empty($job_location_term_ids_sd)) {
    $max_depth = -1;
    foreach ($job_location_term_ids_sd as $loc_id_sd) {
        $term_sd = get_term($loc_id_sd, 'job_location');
        if ($term_sd && !is_wp_error($term_sd)) {
            $ancestors_sd = get_ancestors($term_sd->term_id, 'job_location', 'taxonomy'); $depth_sd = count($ancestors_sd);
            if ($depth_sd > $max_depth) { $most_specific_location_term_sd = $term_sd; $max_depth = $depth_sd; }
        }
    }
}
if ($most_specific_location_term_sd) {
    $address_locality_sd = $most_specific_location_term_sd->name;
    if ($most_specific_location_term_sd->parent) {
        $parent_term_sd = get_term($most_specific_location_term_sd->parent, 'job_location');
        if ($parent_term_sd && !is_wp_error($parent_term_sd)) $address_region_sd = $parent_term_sd->name;
    }
}
$actual_street_address_for_schema_sd = $street_address_full_sd;
if (!empty($address_region_sd) && mb_strpos($actual_street_address_for_schema_sd, $address_region_sd) === 0) {
    $actual_street_address_for_schema_sd = trim(mb_substr($actual_street_address_for_schema_sd, mb_strlen($address_region_sd)));
}
if (!empty($address_locality_sd) && mb_strpos($actual_street_address_for_schema_sd, $address_locality_sd) === 0) {
    $actual_street_address_for_schema_sd = trim(mb_substr($actual_street_address_for_schema_sd, mb_strlen($address_locality_sd)));
}
$job_location_sd_data = ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'JP']];
if (!empty($actual_street_address_for_schema_sd)) $job_location_sd_data['address']['streetAddress'] = $actual_street_address_for_schema_sd;
if (!empty($postal_code_sd)) $job_location_sd_data['address']['postalCode'] = $postal_code_sd;
if (!empty($address_locality_sd)) $job_location_sd_data['address']['addressLocality'] = $address_locality_sd;
if (!empty($address_region_sd)) $job_location_sd_data['address']['addressRegion'] = $address_region_sd;
if (!empty($actual_street_address_for_schema_sd) || !empty($address_locality_sd) || !empty($postal_code_sd) || !empty($address_region_sd)) {
    $structured_data_values['jobLocation'] = $job_location_sd_data;
}

// employmentType (推奨)
$employment_type_sd = '';
switch ($job_type_display_text_sd) {
    case '正社員': $employment_type_sd = 'FULL_TIME'; break;
    case 'パート・アルバイト': $employment_type_sd = 'PART_TIME'; break;
    case '契約社員': $employment_type_sd = 'CONTRACTOR'; break;
    // ... (他の雇用形態) ...
    default: if (!empty($job_type_display_text_sd)) $employment_type_sd = 'OTHER'; break;
}
if (!empty($employment_type_sd)) $structured_data_values['employmentType'] = $employment_type_sd;

// ★★★ baseSalary (推奨) - 単一値の場合は value プロパティを使用する修正 ★★★
$salary_range_sd = get_post_meta($post_id, 'salary_range', true);
$salary_type_sd = get_post_meta($post_id, 'salary_type', true);
$min_salary_sd = null; $max_salary_sd = null; $single_value_salary_sd = null;

if (!empty($salary_range_sd) && !empty($salary_type_sd) && in_array(strtoupper($salary_type_sd), ['HOUR', 'MONTH', 'YEAR'])) {
    $normalized_salary_range = str_replace(['〜', '-', 'ー'], '～', $salary_range_sd);
    $salary_parts_sd = explode('～', $normalized_salary_range);

    $first_part_cleaned = preg_replace('/[^0-9]/', '', $salary_parts_sd[0]);

    if (count($salary_parts_sd) > 1 && trim($salary_parts_sd[1]) !== '') { // 範囲指定の場合
        if (is_numeric($first_part_cleaned) && $first_part_cleaned !== '') {
            $min_salary_sd = (float)$first_part_cleaned;
        }
        $second_part_cleaned = preg_replace('/[^0-9]/', '', $salary_parts_sd[1]);
        if (is_numeric($second_part_cleaned) && $second_part_cleaned !== '') {
            $max_salary_sd = (float)$second_part_cleaned;
        }
    } else { // 単一の値の場合
        if (is_numeric($first_part_cleaned) && $first_part_cleaned !== '') {
            $single_value_salary_sd = (float)$first_part_cleaned;
        }
    }

    // $quantitative_value_data を初期化
    $quantitative_value_data = null;
    if ($single_value_salary_sd !== null) { // 単一値が優先
        $quantitative_value_data = [
            '@type' => 'QuantitativeValue',
            'unitText' => strtoupper($salary_type_sd),
            'value' => $single_value_salary_sd
        ];
    } elseif ($min_salary_sd !== null && $max_salary_sd !== null && $max_salary_sd >= $min_salary_sd) { // 範囲指定
        $quantitative_value_data = [
            '@type' => 'QuantitativeValue',
            'unitText' => strtoupper($salary_type_sd),
            'minValue' => $min_salary_sd,
            'maxValue' => $max_salary_sd
        ];
    } elseif ($min_salary_sd !== null) { // 最小値のみ (範囲上限なし、または単一値として扱われなかった場合)
         $quantitative_value_data = [
            '@type' => 'QuantitativeValue',
            'unitText' => strtoupper($salary_type_sd),
            'minValue' => $min_salary_sd 
            // GoogleはminValueだけでも認識するが、valueの方が適切かもしれない。
            // しかし、ここに来るのは parse エラーか、意図的に上限なしの場合。
            // value にしてしまうと「正確な値」と誤解される可能性もあるので minValue のままにする。
        ];
    }
    
    if ($quantitative_value_data !== null) {
        $base_salary_data_sd = [
            '@type' => 'MonetaryAmount',
            'currency' => 'JPY',
            'value' => $quantitative_value_data
        ];
        $structured_data_values['baseSalary'] = $base_salary_data_sd;
    }
}
// ★★★ baseSalary の修正ここまで ★★★


// responsibilities (職務内容) - プレーンテキスト形式
$responsibilities_content_sd = get_post_meta($post_id, 'contact_info', true);
$responsibilities_text = format_plain_text_for_schema_sd($responsibilities_content_sd);
if (!empty($responsibilities_text)) $structured_data_values['responsibilities'] = $responsibilities_text;

// jobBenefits (福利厚生・待遇) - プレーンテキスト形式
$job_benefits_parts = [];
$benefits_content_sd = get_post_meta($post_id, 'benefits', true);
if (!empty($benefits_content_sd)) $job_benefits_parts[] = format_plain_text_for_schema_sd($benefits_content_sd, "福利厚生");
$holidays_content_sd = get_post_meta($post_id, 'holidays', true);
if (!empty($holidays_content_sd)) $job_benefits_parts[] = format_plain_text_for_schema_sd($holidays_content_sd, "休日・休暇");
$bonus_raise_content_sd = get_post_meta($post_id, 'bonus_raise', true);
if (!empty($bonus_raise_content_sd)) $job_benefits_parts[] = format_plain_text_for_schema_sd($bonus_raise_content_sd, "昇給・賞与");
$job_benefits_text_combined = implode("\n\n", array_filter($job_benefits_parts));
if (!empty($job_benefits_text_combined)) $structured_data_values['jobBenefits'] = $job_benefits_text_combined;

// qualifications (応募資格) - プレーンテキスト形式
$qualifications_content_sd = get_post_meta($post_id, 'requirements', true);
$qualifications_text = format_plain_text_for_schema_sd($qualifications_content_sd);
if (!empty($qualifications_text)) $structured_data_values['qualifications'] = $qualifications_text;

// workHours (勤務時間) - プレーンテキスト形式
$work_hours_content_sd = get_post_meta($post_id, 'working_hours', true);
$work_hours_text = format_plain_text_for_schema_sd($work_hours_content_sd);
if (!empty($work_hours_text)) $structured_data_values['workHours'] = $work_hours_text;

// ★★★ description (HTML形式で、参考イメージに近い構成を目指す) ★★★
$description_html_parts = [];
$allowed_desc_tags = '<p><br><strong><em><ul><ol><li><b><i>'; // description内で許可するHTMLタグ

// 1. 仕事の説明（冒頭のキャッチコピーなど）
$desc_intro_line1_parts = [];
if(isset($structured_data_values['title'])) $desc_intro_line1_parts[] = esc_html($structured_data_values['title']);
if(!empty($job_type_display_text_sd)) $desc_intro_line1_parts[] = esc_html($job_type_display_text_sd);
if(isset($hiring_organization_sd['name'])) $desc_intro_line1_parts[] = '【' . esc_html($hiring_organization_sd['name']) . '】';
if(!empty($desc_intro_line1_parts)) $description_html_parts[] = '<p>' . implode(' / ', $desc_intro_line1_parts) . '</p>';

$job_appeal_points_sd_desc = get_post_meta($post_id, 'job_appeal_points', true); // 仮のCF名
if (!empty($job_appeal_points_sd_desc)) {
    $description_html_parts[] = '<p>' . nl2br(esc_html(trim($job_appeal_points_sd_desc))) . '</p>';
} elseif (get_post_meta($post_id, 'job_content_title', true)){
    $job_catchphrase_sd_desc = get_post_meta($post_id, 'job_content_title', true);
    if(!empty($job_catchphrase_sd_desc)) $description_html_parts[] = '<p><strong>' . esc_html(trim($job_catchphrase_sd_desc)) . '</strong></p>';
}
$description_html_parts[] = '<p>------------------------------------------------</p>';

// 各セクションの情報をHTMLとしてdescriptionに追加
if (isset($structured_data_values['title'])) {
    $description_html_parts[] = format_text_as_html_paragraph_sd('募集職種', $structured_data_values['title']);
    $description_html_parts[] = '<p>------------------------------------------------</p>';
}
if (!empty($qualifications_content_sd)) {
    $description_html_parts[] = format_text_as_html_paragraph_sd('応募資格', $qualifications_content_sd, true);
    $description_html_parts[] = '<p>------------------------------------------------</p>';
}
if (!empty($job_type_display_text_sd)) {
    $description_html_parts[] = format_text_as_html_paragraph_sd('雇用形態', $job_type_display_text_sd);
    $description_html_parts[] = '<p>------------------------------------------------</p>';
}
$retirement_age_sd_desc = get_post_meta($post_id, 'job_retirement_age', true); // 仮のCF名
if (!empty($retirement_age_sd_desc)) {
    $description_html_parts[] = format_text_as_html_paragraph_sd('定年年齢', $retirement_age_sd_desc);
    $description_html_parts[] = '<p>------------------------------------------------</p>';
}
if (!empty($responsibilities_content_sd)) {
    $description_html_parts[] = format_text_as_html_paragraph_sd('仕事内容', $responsibilities_content_sd, true);
    $description_html_parts[] = '<p>------------------------------------------------</p>';
}
$salary_range_for_desc_html = get_post_meta($post_id, 'salary_range', true);
$salary_type_for_desc_html = get_post_meta($post_id, 'salary_type', true);
$salary_remarks_for_desc_html = get_post_meta($post_id, 'salary_remarks', true);
if (!empty($salary_range_for_desc_html)) {
    $salary_display_text = (($salary_type_for_desc_html == 'HOUR') ? '時給 ' : '月給 ') . $salary_range_for_desc_html;
    if (strpos($salary_range_for_desc_html, '円') === false) $salary_display_text .= '円';
    if (!empty($salary_remarks_for_desc_html)) $salary_display_text .= "\n" . trim($salary_remarks_for_desc_html);
    $description_html_parts[] = format_text_as_html_paragraph_sd('給与', $salary_display_text);
    $description_html_parts[] = '<p>------------------------------------------------</p>';
}
if (!empty($benefits_content_sd)) {
    $description_html_parts[] = format_text_as_html_paragraph_sd('福利厚生', $benefits_content_sd, true);
    $description_html_parts[] = '<p>------------------------------------------------</p>';
}
if (!empty($work_hours_content_sd)) {
    $description_html_parts[] = format_text_as_html_paragraph_sd('勤務時間・休憩時間', $work_hours_content_sd, true);
    $description_html_parts[] = '<p>------------------------------------------------</p>';
}
if (!empty($holidays_content_sd)) {
    $description_html_parts[] = format_text_as_html_paragraph_sd('休日休暇', $holidays_content_sd, true);
    $description_html_parts[] = '<p>------------------------------------------------</p>';
}

// メッセージ部分 (WordPressの本文)
$main_content_post_obj_for_desc = get_post($post_id);
if ($main_content_post_obj_for_desc instanceof WP_Post) {
    $message_content_html = apply_filters('the_content', $main_content_post_obj_for_desc->post_content);
    if (!empty(trim(strip_tags($message_content_html)))) {
        $description_html_parts[] = '<p><strong>メッセージ</strong></p>';
        $description_html_parts[] = $message_content_html; // apply_filters の結果はHTMLとして扱う
    }
}

$full_description_html_content = implode("\n", array_filter($description_html_parts));
$final_description_html_output = format_html_content_for_schema_sd($full_description_html_content, $allowed_desc_tags);

if (!empty($final_description_html_output)) {
    $structured_data_values['description'] = $final_description_html_output;
} elseif (empty($structured_data_values['responsibilities']) && isset($structured_data_values['title'])) {
    $structured_data_values['description'] = format_plain_text_for_schema_sd(($structured_data_values['title'] ?? '') . 'に関する求人情報です。');
}


// image (任意)
if (!empty($thumbnail_url_sd) && filter_var($thumbnail_url_sd, FILTER_VALIDATE_URL)) {
   $structured_data_values['image'] = $thumbnail_url_sd;
}

// identifier (任意)
$identifier_name_sd = '';
$facility_company_for_identifier_sd = get_post_meta($post_id, 'facility_company', true);
if (!empty($facility_company_for_identifier_sd)) {
    $identifier_name_sd = $facility_company_for_identifier_sd;
} else {
    $identifier_name_sd = get_bloginfo('name');
}
if (!empty($identifier_name_sd)) {
    $structured_data_values['identifier'] = ['@type' => 'PropertyValue', 'name' => $identifier_name_sd, 'value' => (string)$post_id];
}

// 最終的なJSON-LDデータ配列の構築
$json_ld_output_array = ['@context' => 'http://schema.org/', '@type' => 'JobPosting'];
foreach ($structured_data_values as $key => $value) {
    if (is_string($value)) $value_trimmed = trim($value); else $value_trimmed = $value;
    if (!empty($value_trimmed) || $value_trimmed === 0 || $value_trimmed === 0.0 || (is_array($value_trimmed) && !empty($value_trimmed))) {
        $json_ld_output_array[$key] = $value_trimmed;
    }
}

// 必須プロパティの存在チェックと出力
$all_required_present = isset(
    $json_ld_output_array['title'],
    $json_ld_output_array['datePosted'],
    $json_ld_output_array['hiringOrganization'],
    $json_ld_output_array['jobLocation']
);
if ($all_required_present && !empty($json_ld_output_array['hiringOrganization']['name'])) {
    if (empty($json_ld_output_array['description']) && empty($json_ld_output_array['responsibilities'])) {
        // error_log("Schema Warning: Both description and responsibilities are empty for post ID {$post_id}");
    }
?>
<script type="application/ld+json">
<?php echo json_encode($json_ld_output_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
</script>
<?php
}
// ★★★ 構造化データ準備 ここまで ★★★
?>
</head>-->
<div class="breadcrumb-container">
<?php display_job_breadcrumb(); ?>
</div>
<div class="cont">
    <!-- ヘッダーセクション -->
    <div class="company-name"><?php echo esc_html($facility_company); ?></div>
    <h1 class="job-title1"><?php echo esc_html($job_subtitle); ?></h1>
    <div class="job-subtitle"><?php echo esc_html($job_title); ?></div>
    
    <div class="facility-type">
        <?php
        if (!empty($facility_type)) {
            foreach ($facility_type as $type) {
                echo get_facility_type_icon($type);
            }
        }
        ?>
    </div>
    
    <!-- メイン画像と求人詳細を横並びに -->
    <div class="slideshow-container">
        <div class="slideshow">
    <?php
    // 複数サムネイル画像を取得
    $thumbnail_ids = get_post_meta($post_id, 'job_thumbnail_ids', true);
    
    // 画像がある場合は表示
    if (!empty($thumbnail_ids) && is_array($thumbnail_ids)) {
        foreach ($thumbnail_ids as $thumb_id) {
            $image_url = wp_get_attachment_url($thumb_id);
            if ($image_url) {
                echo '<img src="' . esc_url($image_url) . '" alt="施設画像">';
            }
        }
    } elseif (!empty($gallery_images)) {
        // 互換性のために$gallery_imagesがある場合はそれを使用
        foreach ($gallery_images as $image) {
            echo '<img src="' . esc_url($image) . '" alt="施設画像">';
        }
    } else {
        // サムネイル画像がなければデフォルト画像を表示
        echo '<img src="' . esc_url($thumbnail_url) . '" alt="施設画像">';
    }
    ?>
</div>
        
        <div class="job-details">
            <div class="job-position">
    <span class="position"><?php echo !empty($job_position) ? esc_html($job_position[0]) : ''; ?></span>
    <?php
    $employment_type = !empty($job_type) ? esc_html($job_type[0]) : '';
    $type_class = 'other'; // デフォルトクラス
    
    // 雇用形態によってクラスを設定
    if ($employment_type === '正社員') {
        $type_class = 'full-time';
    } else if ($employment_type === 'パート・アルバイト') {
        $type_class = 'part-time';
    }
    ?>
    <span class="employment-type <?php echo $type_class; ?>"><?php echo $employment_type; ?></span>
</div>
            
            <div class="job-salary">
                <div class="salary-label">住所</div>
                <div class="salary-range">
    <?php 
    // 郵便番号と住所を分けて表示
    $address = esc_html($facility_address);
    // 郵便番号と住所部分を分割
    $address_parts = preg_split('/(\〒\d{3}-\d{4})/', $address, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    
    if (count($address_parts) >= 2) {
        // 郵便番号と住所部分が分かれている場合
        echo $address_parts[0] . "<br>"; // 郵便番号
        echo $address_parts[1]; // 住所部分
    } else {
        // 分割できなかった場合はそのまま表示
        echo $address;
    }
    ?>
</div>
                <div class="salary-label">給与</div>
<div class="salary-range">
    <?php 
    // 賃金形態を取得（MONTH/HOUR）
    $salary_type = get_post_meta($post_id, 'salary_type', true);
    
    // 賃金形態の表示テキスト
    $salary_type_text = '';
    if ($salary_type == 'HOUR') {
        $salary_type_text = '時給 ';
    } else {
        $salary_type_text = '月給 ';
    }
    
    // 給与範囲を表示
    echo esc_html($salary_type_text . $salary_range);
    
    // 「円」を追加（ただし既に「円」が含まれている場合は追加しない）
    if (strpos($salary_range, '円') === false) {
        echo '円';
    }
    ?>
</div>
            
            <!-- ボタン -->
            <div class="button-group">
                <div class="keep-button">★ お気に入り</div>
                <div class="contact-button">応募画面へ</div>
            </div>
        </div>
    </div>
 </div>
    <!-- 情報タブヘッダー -->
    <?php if ($show_info_tabs) : ?>
    <div class="info-tabs">情報目次</div>
    
    <!-- ナビゲーションタブ -->
    <div class="tab-navigation">
        <?php if ($show_job_info) : ?>
        <a href="#job-info" class="active"><i class="fa fa-briefcase"></i>募集内容</a>
        <?php endif; ?>
        
        <?php if ($show_workplace_info) : ?>
        <a href="#workplace-info"><i class="fa fa-hospital"></i>職場の環境</a>
        <?php endif; ?>
        
        <?php if ($show_facility_info) : ?>
        <a href="#facility-info"><i class="fa fa-building"></i>事業所の情報</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- 求人紹介文 -->
    <div class="job-introduction">
        <?php if (!empty($job_content_title)) : ?>
    <h2 class="job-content-title"><?php echo esc_html($job_content_title); ?></h2>
    <?php endif; ?>
    <?php echo wpautop($job_content); ?>
</div>
    
    <div class="content-area">
        <!-- メインコンテンツ -->
        <div class="main-content">
            <!-- 求人詳細情報 -->
            <?php if ($show_job_info) : ?>
            <div id="job-info" class="job-descripti">
                <h2 class="section-title">募集情報</h2>
                <table class="job-info-table">
                    <tr>
                        <th>職種名称</th>
                        <td><?php echo !empty($job_position) ? esc_html($job_position[0]) : ''; ?></td>
                    </tr>
                    <tr>
                        <th>雇用形態</th>
                        <td><?php echo !empty($job_type) ? esc_html($job_type[0]) : ''; ?></td>
                    </tr>
                    <tr>
                      <th>給与</th>
                      <td>
                        <?php 
                        // 賃金形態を取得（MONTH/HOUR）
                        $salary_type = get_post_meta($post_id, 'salary_type', true);
                        $salary_form = get_post_meta($post_id, 'salary_form', true);
                        $salary_remarks = get_post_meta($post_id, 'salary_remarks', true);
                        
                        // 賃金形態の表示テキスト
                        $salary_type_text = '';
                        if ($salary_type == 'HOUR') {
                          $salary_type_text = '時給 ';
                        } else {
                          $salary_type_text = '月給 ';
                        }
                        
                        // 給与範囲を表示
                        echo esc_html($salary_type_text . $salary_range);
                        
                        // 「円」を追加（ただし既に「円」が含まれている場合は追加しない）
                        if (strpos($salary_range, '円') === false) {
                          echo '円';
                        }
                        
                        // 給与についての備考があれば表示
                        if (!empty($salary_remarks)) {
                          echo '<div class="salary-remarks">';
                          echo nl2br(esc_html($salary_remarks));
                          echo '</div>';
                        }
                        ?>
                      </td>
                    </tr>
                    <tr>
                        <th>仕事内容</th>
                        <td><?php echo nl2br(esc_html($contact_info)); ?></td>
                    </tr>
                    <?php if (!empty($requirements)) : ?>
                    <tr>
                        <th>応募要件</th>
                        <td><?php echo nl2br(esc_html($requirements)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>勤務時間</th>
                        <td><?php echo nl2br(esc_html($working_hours)); ?></td>
                    </tr>
                    <tr>
                        <th>休日・休暇</th>
                        <td><?php echo nl2br(esc_html($holidays)); ?></td>
                    </tr>
                    <?php if (!empty($benefits)) : ?>
                    <tr>
                        <th>福利厚生</th>
                        <td><?php echo nl2br(esc_html($benefits)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($bonus_raise)) : ?>
                    <tr>
                        <th>昇給・賞与</th>
                        <td><?php echo nl2br(esc_html($bonus_raise)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($application_process)) : ?>
<tr>
    <th>選考プロセス</th>
    <td>
           <?php 
        echo nl2br(esc_html($application_process));
        ?>
    </td>
</tr>
<?php endif; ?>
                </table>
                
                <!-- 求人タグ - 修正済み -->
                <?php if (!empty($job_feature)) : ?>
                <div class="feature-section">
                    <h3 class="feature-title">この求人の特徴</h3>
                    <div class="tag-container">
                        <?php foreach ($job_feature as $feature) : ?>
                            <div class="job-tag"><?php echo esc_html($feature); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- 職場環境 - 修正済み -->
            <?php if ($show_workplace_info) : ?>
            <div id="workplace-info" class="workplace-environment">
                <h2 class="environment-title">職場の環境</h2>
                
                <!-- 一日のスケジュール -->
                <?php if (!empty($daily_schedule_items)) : ?>
                <div class="schedule-section">
                    <h3 class="section-subtitle"><span class="orange-dot"></span>仕事の一日の流れ</h3>
                    <div class="daily-schedule">
                        <?php
                        $schedule_items = maybe_unserialize($daily_schedule_items);
                        if (is_array($schedule_items)) :
                            foreach ($schedule_items as $item) :
                                // 時間表示の種類を判定（「〜」で始まる場合は白枠、それ以外はオレンジ）
                                $time_class = (strpos($item['time'], '〜') === 0) ? 'timeline-time-white' : 'timeline-time-orange';
                        ?>
                        <div class="timeline-row">
                            <div class="<?php echo $time_class; ?>"><?php echo esc_html($item['time']); ?></div>
                            <div class="timeline-content">
                                <div class="timeline-title"><?php echo esc_html($item['title']); ?></div>
                                <?php if (!empty($item['description'])) : ?>
                                <div class="timeline-description"><?php echo esc_html($item['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- スタッフの声 -->
               <?php
               
               if (!empty($staff_voice_items) && is_array($staff_voice_items)) : 
               ?>
               <div class="voice-section">
                   <h3 class="section-subtitle"><span class="orange-dot"></span>職員の声</h3>
                   <div class="staff-voices-container">
                       <?php foreach ($staff_voice_items as $voice) : 
                           if (empty($voice['role'])) continue;
                           
                           // 画像URLの取得
                           $image_url = '';
                           if (!empty($voice['image_id'])) {
                               $image_url = wp_get_attachment_url($voice['image_id']);
                           }
                       ?>
                       <div class="staff-voice">
                           <div class="staff-info">
                               <div class="staff-photo">
                                   <?php if (!empty($image_url)) : ?>
                                   <img src="<?php echo esc_url($image_url); ?>" alt="スタッフ画像" class="staff-img">
                                   <?php else : ?>
                                   <img src="<?php echo get_template_directory_uri(); ?>/assets/images/no-staff-image.jpg" alt="スタッフ画像なし" class="staff-img">
                                   <?php endif; ?>
                               </div>
                               <div class="staff-details">
                                   <div class="staff-role"><span class="staff-label">職種：</span><?php echo esc_html($voice['role']); ?></div>
                                   <div class="staff-years"><span class="staff-label">勤続年数：</span><?php echo esc_html($voice['years']); ?></div>
                               </div>
                           </div>
                           <div class="staff-comment"><?php echo nl2br(esc_html($voice['comment'])); ?></div>
                       </div>
                       <?php endforeach; ?>
                   </div>
               </div>
               <?php endif; ?>
           </div>
           <?php endif; ?>
           
           <!-- 施設詳細 -->
           <?php if ($show_facility_info) : ?>
           <div id="facility-info" class="facility-details">
               <h2 class="section-title">事業所の情報</h2>
               <table class="facility-info-table">
                   <tr>
                       <th>施設名</th>
                       <td><?php echo esc_html($facility_name); ?></td>
                   </tr>
                   <tr>
                       <th>住所</th>
                       <td>
                           <?php 
                           // 郵便番号と住所を分けて表示
                           $address = esc_html($facility_address);
                           // 郵便番号と住所部分を分割
                           $address_parts = preg_split('/(\〒\d{3}-\d{4})/', $address, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                           
                           if (count($address_parts) >= 2) {
                               // 郵便番号と住所部分が分かれている場合
                               echo $address_parts[0] . "<br>"; // 郵便番号
                               echo $address_parts[1]; // 住所部分
                           } else {
                               // 分割できなかった場合はそのまま表示
                               echo $address;
                           }
                           ?>
                       </td>
                   </tr>
                   <?php if (!empty($facility_map)) : ?>
                   <tr>
                       <th>MAP</th>
                       <td>
                           <div class="map-container">
                               <?php echo $facility_map; // 地図埋め込みコード ?>
                           </div>
                       </td>
                   </tr>
                   <?php endif; ?>
                   <?php if (!empty($facility_type)) : ?>
                   <tr>
                       <th>サービス種別</th>
                       <td><?php echo esc_html(implode('・', $facility_type)); ?></td>
                   </tr>
                   <?php endif; ?>
                   <?php if (!empty($capacity)) : ?>
<tr>
    <th>利用定員数</th>
    <td><?php echo esc_html($capacity); ?></td>
</tr>
<?php endif; ?>
                   <?php if (!empty($staff_composition)) : ?>
<tr>
    <th>スタッフ構成</th>
    <td class="facility-staff">
        <?php 
        $staff_items = explode("\n", $staff_composition);
        foreach ($staff_items as $staff) :
            if (trim($staff) !== '') :
        ?>
            <div><?php echo esc_html(trim($staff)); ?></div>
        <?php 
            endif;
        endforeach;
        ?>
    </td>
</tr>
<?php endif; ?>
                   <?php if (!empty($facility_tel)) : ?>
                   <tr>
                       <th>電話番号</th>
                       <td><?php echo esc_html($facility_tel); ?></td>
                   </tr>
                   <?php endif; ?>
                   <?php if (!empty($facility_hours)) : ?>
                   <tr>
                       <th>営業時間</th>
                       <td><?php echo nl2br(esc_html($facility_hours)); ?></td>
                   </tr>
                   <?php endif; ?>
                   <?php if (!empty($facility_url)) : ?>
                   <tr>
                       <th>施設URL</th>
                       <td><a href="<?php echo esc_url($facility_url); ?>" target="_blank"><?php echo esc_url($facility_url); ?></a></td>
                   </tr>
                   <?php endif; ?>
                   <tr>
                       <th>運営会社名</th>
                       <td><?php echo esc_html($facility_company); ?></td>
                   </tr>
                   <?php if (!empty($company_url)) : ?>
                   <tr>
                       <th>運営会社URL</th>
                       <td><a href="<?php echo esc_url($company_url); ?>" target="_blank"><?php echo esc_url($company_url); ?></a></td>
                   </tr>
                   <?php endif; ?>
               </table>
           </div>
           <?php endif; ?>
       </div>
       
<!-- サイドバー -->
<div class="custom-sidebar">
   <?php
   // 同じエリア・職種の求人の検索ロジック
   $current_location_terms = array();
   $location_slug_for_url = '';
   $location_name_for_display = '';
   $search_location_ids = array();
   $most_specific_term = null;
   
   // 最も詳細なターム（孫＞子＞親）を特定
   if (!empty($job_location_ids)) {
       $max_depth = -1;
       
       foreach ($job_location_ids as $loc_id) {
           $term = get_term($loc_id, 'job_location');
           if (!is_wp_error($term)) {
               // そのタームの階層の深さを取得
               $ancestors = get_ancestors($term->term_id, 'job_location', 'taxonomy');
               $depth = count($ancestors);
               
               // より深い階層（詳細）のタームを選択
               if ($depth > $max_depth) {
                   $most_specific_term = $term;
                   $max_depth = $depth;
               }
               
               $current_location_terms[] = $term;
           }
       }
       
       // 最も詳細なタームが見つかった場合
       if ($most_specific_term) {
           $location_slug_for_url = $most_specific_term->slug;
           $location_name_for_display = $most_specific_term->name;
           $search_location_ids = array($most_specific_term->term_id);
       }
   }
   
   // 同じエリア・職種の求人を取得（3個に制限）
   $area_job_args = array(
       'post_type' => 'job',
       'posts_per_page' => 3,
       'post__not_in' => array($post_id),
       'tax_query' => array(
           'relation' => 'AND',
       ),
   );
   
   // 職種条件を追加
   if (!empty($job_position_ids)) {
       $area_job_args['tax_query'][] = array(
           'taxonomy' => 'job_position',
           'field'    => 'id',
           'terms'    => $job_position_ids,
       );
   }
   
   // エリア条件を追加
   if (!empty($search_location_ids)) {
       $area_job_args['tax_query'][] = array(
           'taxonomy' => 'job_location',
           'field'    => 'id',
           'terms'    => $search_location_ids,
       );
   }
   
   $area_job_query = new WP_Query($area_job_args);
   
   // 同じ詳細エリアでの検索が結果を返さなかった場合、親エリアで再検索
   if (!$area_job_query->have_posts() && !empty($most_specific_term)) {
       wp_reset_postdata();
       
       // 親タームを取得
       $ancestors = get_ancestors($most_specific_term->term_id, 'job_location', 'taxonomy');
       
       if (!empty($ancestors)) {
           $parent_id = $ancestors[0]; // 直接の親
           $parent_term = get_term($parent_id, 'job_location');
           
           if (!is_wp_error($parent_term)) {
               // 親タームで再検索
               $location_slug_for_url = $parent_term->slug;
               $location_name_for_display = $parent_term->name;
               
               $area_job_args['tax_query'] = array(
                   'relation' => 'AND',
               );
               
               // 職種条件を追加
               if (!empty($job_position_ids)) {
                   $area_job_args['tax_query'][] = array(
                       'taxonomy' => 'job_position',
                       'field'    => 'id',
                       'terms'    => $job_position_ids,
                   );
               }
               
               // 親エリア条件を追加
               $area_job_args['tax_query'][] = array(
                   'taxonomy' => 'job_location',
                   'field'    => 'id',
                   'terms'    => $parent_id,
               );
               
               $area_job_query = new WP_Query($area_job_args);
               
               // 親での検索も結果がなかった場合、さらに上の親（祖父）で検索
               if (!$area_job_query->have_posts() && count($ancestors) > 1) {
                   wp_reset_postdata();
                   
                   $grandparent_id = $ancestors[1];
                   $grandparent_term = get_term($grandparent_id, 'job_location');
                   
                   if (!is_wp_error($grandparent_term)) {
                       $location_slug_for_url = $grandparent_term->slug;
                       $location_name_for_display = $grandparent_term->name;
                       
                       $area_job_args['tax_query'] = array(
                           'relation' => 'AND',
                       );
                       
                       // 職種条件を追加
                       if (!empty($job_position_ids)) {
                           $area_job_args['tax_query'][] = array(
                               'taxonomy' => 'job_position',
                               'field'    => 'id',
                               'terms'    => $job_position_ids,
                           );
                       }
                       
                       // 祖父エリア条件を追加
                       $area_job_args['tax_query'][] = array(
                           'taxonomy' => 'job_location',
                           'field'    => 'id',
                           'terms'    => $grandparent_id,
                       );
                       
                       $area_job_query = new WP_Query($area_job_args);
                   }
               }
           }
       }
   }
   
   // 記事があるかチェック
   $has_area_jobs = $area_job_query->have_posts();
   
   // 同じ施設の別の求人を検索
   $related_args = array(
       'post_type' => 'job',
       'posts_per_page' => 3,
       'post__not_in' => array($post_id),
       'meta_query' => array(
           array(
               'key' => 'facility_name',
               'value' => $facility_name,
               'compare' => '='
           )
       )
   );
   $related_query = new WP_Query($related_args);
   
   // 記事があるかチェック
   $has_facility_jobs = $related_query->have_posts();
   
   // ブログの取得
$blog_url = '';
$found_blog_posts = array();
$blog_post_limit = 3;

if (!empty($facility_url)) {
    $blog_url = trailingslashit($facility_url) . 'blog/';
    
    // まずRSSフィードから取得を試みる
    if (function_exists('fetch_feed')) {
        $rss_url = $blog_url . 'feed/';
        $rss = fetch_feed($rss_url);
        
        if (!is_wp_error($rss)) {
            $max_items = $rss->get_item_quantity($blog_post_limit);
            $rss_items = $rss->get_items(0, $max_items);
            
            if (!empty($rss_items)) {
                foreach ($rss_items as $item) {
                    // 画像を取得
                    $image_url = '';
                    $content = $item->get_content();
                    
                    // より広範囲な画像検出パターン
                    $image_patterns = array(
                        '/<img[^>]+src=[\'"]([^\'">]+)[\'"][^>]*>/i',
                        '/<img[^>]+src=([^\'"> ]+)[^>]*>/i',
                        '/background-image:\s*url\([\'"]?([^\'")]+)[\'"]?\)/i'
                    );
                    
                    foreach ($image_patterns as $pattern) {
                        if (preg_match($pattern, $content, $matches)) {
                            $image_url = $matches[1];
                            break;
                        }
                    }
                    
                    // 相対URLを絶対URLに変換
                    if (!empty($image_url) && strpos($image_url, 'http') !== 0) {
                        $base_url = preg_replace('/\/blog\/?$/', '', $facility_url);
                        $image_url = rtrim($base_url, '/') . '/' . ltrim($image_url, '/');
                    }
                    
                    $found_blog_posts[] = array(
                        'title' => $item->get_title(),
                        'url' => $item->get_permalink(),
                        'image' => $image_url,
                        'date' => $item->get_date('Y年m月d日'),
                        'category' => '',
                    );
                    
                    if (count($found_blog_posts) >= $blog_post_limit) {
                        break;
                    }
                }
            }
        }
    }
    
    // RSSフィードから十分な記事が取得できなかった場合、HTMLから直接取得
    if (count($found_blog_posts) < $blog_post_limit) {
        $blog_content = wp_remote_get($blog_url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress)'
        ));
        
        if (!is_wp_error($blog_content) && 200 === wp_remote_retrieve_response_code($blog_content)) {
            $html = wp_remote_retrieve_body($blog_content);
            
            // 様々なパターンでブログ記事を検索
            $article_patterns = array(
                // パターン1: staff-cttクラス
                '/<div class=[\'"]staff-ctt[\'"][^>]*>(.*?)<\/div>\s*<\/div>/is',
                // パターン2: 記事リストアイテム
                '/<article[^>]*>(.*?)<\/article>/is',
                // パターン3: リスト項目
                '/<li[^>]*class=[\'"][^\'"]*(post|article|entry)[^\'"]?[\'"][^>]*>(.*?)<\/li>/is',
                // パターン4: divベースの記事
                '/<div[^>]*class=[\'"][^\'"]*(post|article|entry)[^\'"]?[\'"][^>]*>(.*?)<\/div>/is'
            );
            
            $articles_found = array();
            
            foreach ($article_patterns as $pattern) {
                preg_match_all($pattern, $html, $matches);
                if (!empty($matches[1])) {
                    $articles_found = array_merge($articles_found, $matches[1]);
                }
                
                // 十分な記事が見つかったら停止
                if (count($articles_found) >= $blog_post_limit) {
                    break;
                }
            }
            
            // 記事からデータを抽出
            foreach ($articles_found as $article_content) {
                if (count($found_blog_posts) >= $blog_post_limit) {
                    break;
                }
                
                $post_data = array(
                    'title' => '',
                    'url' => '',
                    'image' => '',
                    'date' => '',
                    'category' => '',
                );
                
                // リンクURLを取得（複数パターン）
                $link_patterns = array(
                    '/<a[^>]+href=[\'"]([^\'">]+)[\'"][^>]*>/i',
                    '/href=[\'"]([^\'">]+)[\'"]/',
                );
                
                foreach ($link_patterns as $pattern) {
                    if (preg_match($pattern, $article_content, $url_matches)) {
                        $post_data['url'] = $url_matches[1];
                        break;
                    }
                }
                
                // 相対URLの場合は絶対URLに変換
                if (!empty($post_data['url']) && strpos($post_data['url'], 'http') !== 0) {
                    $base_url = preg_replace('/\/blog\/?$/', '', $facility_url);
                    $post_data['url'] = rtrim($base_url, '/') . '/' . ltrim($post_data['url'], '/');
                }
                
                // 画像を取得（複数パターン）
                $image_patterns = array(
                    '/<img[^>]+src=[\'"]([^\'">]+)[\'"][^>]*>/i',
                    '/background-image:\s*url\([\'"]?([^\'")]+)[\'"]?\)/i',
                    '/data-src=[\'"]([^\'">]+)[\'"]/',  // 遅延読み込み対応
                    '/srcset=[\'"]([^\'">]+)[\'"]/',    // レスポンシブ画像対応
                );
                
                foreach ($image_patterns as $pattern) {
                    if (preg_match($pattern, $article_content, $img_matches)) {
                        $potential_image = $img_matches[1];
                        
                        // srcsetの場合は最初の画像URLを取得
                        if (strpos($potential_image, ',') !== false) {
                            $srcset_parts = explode(',', $potential_image);
                            $potential_image = trim(explode(' ', trim($srcset_parts[0]))[0]);
                        }
                        
                        $post_data['image'] = $potential_image;
                        break;
                    }
                }
                
                // 相対URLの画像を絶対URLに変換
                if (!empty($post_data['image']) && strpos($post_data['image'], 'http') !== 0) {
                    $base_url = preg_replace('/\/blog\/?$/', '', $facility_url);
                    $post_data['image'] = rtrim($base_url, '/') . '/' . ltrim($post_data['image'], '/');
                }
                
                // タイトルを取得（複数パターン）
                $title_patterns = array(
                    '/<span class=[\'"]new[\'"]>(.*?)<\/span>/is',
                    '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is',
                    '/<[^>]*title=[\'"]([^\'">]+)[\'"][^>]*>/i',
                    '/<a[^>]*>([^<]+)<\/a>/i',
                );
                
                foreach ($title_patterns as $pattern) {
                    if (preg_match($pattern, $article_content, $title_matches)) {
                        $post_data['title'] = strip_tags($title_matches[1]);
                        if (!empty($post_data['title'])) {
                            break;
                        }
                    }
                }
                
                // 日付を取得（複数パターン）
                $date_patterns = array(
                    '/<small>(.*?)<\/small>/is',
                    '/<time[^>]*>(.*?)<\/time>/is',
                    '/(\d{4}[-\/]\d{1,2}[-\/]\d{1,2})/',
                    '/(\d{4}年\d{1,2}月\d{1,2}日)/',
                );
                
                foreach ($date_patterns as $pattern) {
                    if (preg_match($pattern, $article_content, $date_matches)) {
                        $post_data['date'] = strip_tags($date_matches[1]);
                        if (!empty($post_data['date'])) {
                            break;
                        }
                    }
                }
                
                // カテゴリーを取得
                $category_patterns = array(
                    '/<div class=[\'"]post-category[\'"]>(.*?)<\/div>/is',
                    '/<span class=[\'"]category[\'"]>(.*?)<\/span>/is',
                );
                
                foreach ($category_patterns as $pattern) {
                    if (preg_match($pattern, $article_content, $cat_matches)) {
                        $post_data['category'] = strip_tags($cat_matches[1]);
                        break;
                    }
                }
                
                // 有効なタイトルとURLがあれば追加
                if (!empty($post_data['title']) && !empty($post_data['url'])) {
                    $found_blog_posts[] = $post_data;
                }
            }
        }
    }
}

// ブログ記事が見つかったかチェック
$has_blog_posts = !empty($found_blog_posts);


// デバッグ: 最終的に見つかった記事をログに出力
error_log('Final blog posts found: ' . count($found_blog_posts));
foreach ($found_blog_posts as $index => $post) {
    error_log("Blog post $index: Title='{$post['title']}', Image='{$post['image']}', URL='{$post['url']}'");
}
   
   // 職種スラッグを取得 (「もっと見る」リンク用)
   $position_slug = '';
   if (!empty($job_position_ids)) {
       $position_term = get_term($job_position_ids[0], 'job_position');
       if (!is_wp_error($position_term)) {
           $position_slug = $position_term->slug;
       }
   }
   
   // 検索一覧へのリンク構築
   $search_url = home_url('/jobs/');
   if (!empty($location_slug_for_url)) {
       $search_url .= 'location/' . $location_slug_for_url . '/';
       
       if (!empty($position_slug)) {
           $search_url .= 'position/' . $position_slug . '/';
       }
   } elseif (!empty($position_slug)) {
       $search_url .= 'position/' . $position_slug . '/';
   }
   
   // 施設名でのキーワード検索URL
   $facility_search_url = home_url('/') . '?s=' . urlencode($facility_name) . '&post_type=job';
   ?>
   
   <?php if ($has_area_jobs) : // 同じエリア・職種の求人がある場合のみ表示 ?>
   <!-- 同じエリア・同じ職種の求人 -->
   <div class="related-jobs">
       <h3>同じエリア・職種の求人</h3>
       <?php
       $post_count = 0;
       // 【修正後】
while ($area_job_query->have_posts() && $post_count < 3) : $area_job_query->the_post();
    $post_count++;
    $rel_facility = get_post_meta(get_the_ID(), 'facility_name', true);
    
    // 関連求人の職種・雇用形態も「その他」対応で取得
    $rel_position_text = get_job_position_display_text(get_the_ID());
    $rel_type_text = get_job_type_display_text(get_the_ID());
    
    // 配列形式で格納（既存コードとの互換性）
    $rel_position = !empty($rel_position_text) ? array($rel_position_text) : array();
    $rel_type = !empty($rel_type_text) ? array($rel_type_text) : array();
    
    $rel_thumb = get_the_post_thumbnail_url(get_the_ID(), 'medium');
    if (!$rel_thumb) {
        $rel_thumb = get_template_directory_uri() . '/assets/images/no-image.jpg';
    }
       ?>
       <div class="related-job-item">
           <a href="<?php the_permalink(); ?>">
               <div class="related-job-thumb">
                   <img src="<?php echo esc_url($rel_thumb); ?>" alt="関連求人">
               </div>
               <div class="related-job-title"><?php echo esc_html($rel_facility); ?></div>
               <div class="related-job-subtitle">
                   <?php 
                   echo !empty($rel_position) ? esc_html($rel_position[0]) : ''; 
                   echo !empty($rel_type) ? '（' . esc_html($rel_type[0]) . '）' : '';
                   ?>
               </div>
           </a>
       </div>
       <?php
       endwhile;
       wp_reset_postdata();
       ?>
       <a href="<?php echo esc_url($search_url); ?>" class="see-more">もっと見る</a>
   </div>
   <?php endif; ?>
   
   <?php if ($has_facility_jobs) : // 同じ施設の求人がある場合のみ表示 ?>
   <!-- 同じ施設の別の求人 -->
   <div class="related-jobs">
       <h3>同じ施設の求人</h3>
       <?php
       $post_count = 0;
       while ($related_query->have_posts() && $post_count < 3) : $related_query->the_post();
    $post_count++;
    
    // 関連求人の職種・雇用形態も「その他」対応で取得
    $rel_position_text = get_job_position_display_text(get_the_ID());
    $rel_type_text = get_job_type_display_text(get_the_ID());
    
    // 配列形式で格納（既存コードとの互換性）
    $rel_position = !empty($rel_position_text) ? array($rel_position_text) : array();
    $rel_type = !empty($rel_type_text) ? array($rel_type_text) : array();
    
    $rel_thumb = get_the_post_thumbnail_url(get_the_ID(), 'medium');
    if (!$rel_thumb) {
        $rel_thumb = get_template_directory_uri() . '/assets/images/no-image.jpg';
    }
       ?>
       <div class="related-job-item">
           <a href="<?php the_permalink(); ?>">
               <div class="related-job-thumb">
                   <img src="<?php echo esc_url($rel_thumb); ?>" alt="関連求人">
               </div>
               <div class="related-job-title"><?php echo esc_html($facility_name); ?></div>
               <div class="related-job-subtitle">
                   <?php 
                   echo !empty($rel_position) ? esc_html($rel_position[0]) : ''; 
                   echo !empty($rel_type) ? '（' . esc_html($rel_type[0]) . '）' : '';
                   ?>
               </div>
           </a>
       </div>
       <?php
       endwhile;
       wp_reset_postdata();
       ?>
       <a href="<?php echo esc_url($facility_search_url); ?>" class="see-more">もっと見る</a>
   </div>
   <?php endif; ?>
   
   <?php if ($has_blog_posts && !empty($blog_url)) : // ブログ記事がある場合のみ表示 ?>
   <!-- 施設のブログ記事セクション -->
   <div class="related-jobs">
    <h3><?php echo esc_html($facility_name); ?>のブログ</h3>
    <?php foreach ($found_blog_posts as $index => $post_data) : ?>
    <div class="related-job-item">
        <a href="<?php echo esc_url($post_data['url']); ?>" target="_blank">
            <div class="related-job-thumb">
                <?php if (!empty($post_data['image'])) : ?>
                    <img src="<?php echo esc_url($post_data['image']); ?>" 
                         alt="<?php echo esc_attr($post_data['title']); ?>"
                         onerror="this.onerror=null; this.src='<?php echo get_template_directory_uri(); ?>/assets/images/blog-default.jpg'; console.log('Blog image failed to load: <?php echo esc_js($post_data['image']); ?>');"
                         onload="console.log('Blog image loaded successfully: <?php echo esc_js($post_data['image']); ?>');">
                <?php else : ?>
                    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/blog-default.jpg" 
                         alt="ブログ画像（デフォルト）">
                    <script>console.log('No image URL found for blog post: <?php echo esc_js($post_data['title']); ?>');</script>
                <?php endif; ?>
            </div>
            <?php if (!empty($post_data['category'])) : ?>
            <div class="post-category"><?php echo esc_html($post_data['category']); ?></div>
            <?php endif; ?>
            <div class="related-job-title"><?php echo esc_html($post_data['title']); ?></div>
            <div class="related-job-subtitle"><?php echo esc_html($post_data['date']); ?></div>
        </a>
    </div>
<?php endforeach; ?>
    <a href="<?php echo esc_url($blog_url); ?>" target="_blank" class="see-more">もっと見る</a>
</div>
<?php endif; ?>   

<!-- JavaScriptの追加 -->
<script>
jQuery(document).ready(function($) {
   // ナビゲーションタブの切り替え
   $('.tab-navigation a').on('click', function(e) {
       e.preventDefault();
       var target = $(this).attr('href');
       
       // アクティブクラスの切り替え
       $('.tab-navigation a').removeClass('active');
       $(this).addClass('active');
       
       // スクロール処理
       $('html, body').animate({
           scrollTop: $(target).offset().top - 100
       }, 500);
   });
   
   // キープボタン処理
   $('.keep-button').on('click', function() {
       var postId = <?php echo $post_id; ?>;
       var button = $(this);
       
       <?php if (is_user_logged_in()) : ?>
           // ログイン済みユーザーの場合はAJAXでサーバーに保存
           $.ajax({
               url: '<?php echo admin_url('admin-ajax.php'); ?>',
               type: 'POST',
               data: {
                   action: 'toggle_job_favorite',
                   job_id: postId,
                   nonce: '<?php echo wp_create_nonce('job_favorite_nonce'); ?>'
               },
               beforeSend: function() {
                   button.prop('disabled', true);
               },
               success: function(response) {
                   if (response.success) {
                       if (response.data.favorited) {
                           button.text('★ お気に入り済み');
                           button.css('background-color', '#fff3e0');
                       } else {
                           button.text('★ お気に入り');
                           button.css('background-color', '#fff');
                       }
                   } else {
                       alert('エラーが発生しました: ' + response.data.message);
                   }
               },
               error: function() {
                   alert('通信エラーが発生しました。');
               },
               complete: function() {
                   button.prop('disabled', false);
               }
           });
       <?php else : ?>
           // 未ログインユーザーの場合は会員登録ページへリダイレクト
           window.location.href = '<?php echo home_url('/register/'); ?>';
           return false;
       <?php endif; ?>
   });
   
   // 既にキープされているかチェック
   var currentPostId = <?php echo $post_id; ?>;
   <?php if (is_user_logged_in()) : ?>
       // ログイン済みユーザーの場合はユーザーメタから取得
       var userFavorites = <?php echo json_encode(get_user_meta(get_current_user_id(), 'user_favorites', true) ?: array()); ?>;
       
       if (userFavorites.includes(currentPostId)) {
           $('.keep-button').text('★ お気に入り済み');
           $('.keep-button').css('background-color', '#fff3e0');
       }
   <?php else : ?>
       // 未ログインユーザーの場合：キープボタンの表示は通常のまま
       // localStorageの処理は削除
   <?php endif; ?>
   
   // お問い合わせボタン処理
   $('.contact-button').on('click', function() {
       // 応募フォームへのリンク
       window.location.href = '<?php echo esc_url(home_url('/apply/?job_id=' . $post_id)); ?>';
   });
});
   
jQuery(document).ready(function($) {
   // キープボタン処理（元のJS処理と同様）
   $('.keep-button-footer').on('click', function() {
       var postId = <?php echo $post_id; ?>;
       var button = $(this);
       
       <?php if (is_user_logged_in()) : ?>
           // ログイン済みユーザーの場合はAJAXでサーバーに保存
           $.ajax({
               url: '<?php echo admin_url('admin-ajax.php'); ?>',
               type: 'POST',
               data: {
                   action: 'toggle_job_favorite',
                   job_id: postId,
                   nonce: '<?php echo wp_create_nonce('job_favorite_nonce'); ?>'
               },
               beforeSend: function() {
                   button.prop('disabled', true);
               },
               success: function(response) {
                   if (response.success) {
                       if (response.data.favorited) {
                           button.text('★ お気に入り済み');
                           button.addClass('kept');
                           // メイン画面のボタンも更新
                           $('.keep-button').text('★ お気に入り済み');
                           $('.keep-button').css('background-color', '#fff3e0');
                       } else {
                           button.text('★ お気に入り');
                           button.removeClass('kept');
                           // メイン画面のボタンも更新
                           $('.keep-button').text('★ お気に入り');
                           $('.keep-button').css('background-color', '#fff');
                       }
                   } else {
                       alert('エラーが発生しました: ' + response.data.message);
                   }
               },
               error: function() {
                   alert('通信エラーが発生しました。');
               },
               complete: function() {
                   button.prop('disabled', false);
               }
           });
       <?php else : ?>
           // 未ログインユーザーの場合は会員登録ページへリダイレクト
           window.location.href = '<?php echo home_url('/register/'); ?>';
           return false;
       <?php endif; ?>
   });
   
   // 既にキープされているかチェック
   var currentPostId = <?php echo $post_id; ?>;
   <?php if (is_user_logged_in()) : ?>
       // ログイン済みユーザーの場合はユーザーメタから取得
       var userFavorites = <?php echo json_encode(get_user_meta(get_current_user_id(), 'user_favorites', true) ?: array()); ?>;
       
       if (userFavorites.includes(currentPostId)) {
           $('.keep-button-footer').text('★ お気に入り済み');
           $('.keep-button-footer').addClass('kept');
       }
   <?php endif; ?>
   
   // お問い合わせボタン処理
   $('.contact-button-footer').on('click', function() {
       // 応募フォームへのリンク
       window.location.href = '<?php echo esc_url(home_url('/apply/?job_id=' . $post_id)); ?>';
   });
});	
</script>
<!-- 固定フッターバー -->
<div class="fixed-footer-bar">
   <div class="fixed-footer-container">
       <div class="footer-job-info">
           <p class="footer-job-title"><?php echo esc_html($facility_name); ?></p>
           <p class="footer-job-position"><?php echo !empty($job_position) ? esc_html($job_position[0]) : ''; ?> / <?php echo !empty($job_type) ? esc_html($job_type[0]) : ''; ?></p>
       </div>
       <div class="footer-buttons">
           <div class="keep-button-footer">★ お気に入り</div>
           <div class="contact-button-footer">応募画面へ</div>
       </div>
   </div>
</div>


<?php 
// フッターからデフォルトのウィジェットを削除
if (function_exists('remove_action')) {
   remove_action('wp_footer', 'wp_widget_recent_entries_render');
   remove_action('wp_footer', 'wp_widget_categories_render');
   remove_action('wp_footer', 'wp_widget_popular_posts_render');
}

get_footer();
?>