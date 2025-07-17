<?php //子テーマ用関数
if (!defined('ABSPATH')) exit;

//子テーマ用のビジュアルエディタースタイルを適用
add_editor_style();

//以下に子テーマ用の関数を書く
// 会員登録画面からユーザー名を取り除く
add_filter( 'wpmem_register_form_rows', function( $rows ) {
    unset( $rows['username'] );
    return $rows;
});
// メールアドレスからユーザー名を作成する
add_filter( 'wpmem_pre_validate_form', function( $fields ) {
    $fields['username'] = $fields['user_email'];
    return $fields;
});

//会員登録時に（登録者へ）送信されるメールを停止する
add_filter( 'wp_new_user_notification_email', '__return_false' );

// WP-Members関連のエラーを抑制する関数
function suppress_wpmembers_errors() {
    // エラーハンドラー関数を定義
    function custom_error_handler($errno, $errstr, $errfile) {
        // WP-Membersプラグインのエラーを抑制
        if (strpos($errfile, 'wp-members') !== false || 
            strpos($errfile, 'email-as-username-for-wp-members') !== false) {
            // 特定のエラーメッセージのみを抑制
            if (strpos($errstr, 'Undefined array key') !== false) {
                return true; // エラーを抑制
            }
        }
        // その他のエラーは通常通り処理
        return false;
    }
    
    // エラーハンドラーを設定（警告と通知のみ）
    set_error_handler('custom_error_handler', E_WARNING | E_NOTICE);
}

// フロントエンド表示時のみ実行
if (!is_admin() && !defined('DOING_AJAX')) {
    add_action('init', 'suppress_wpmembers_errors', 1);
}


// タクソノミーの子ターム取得用Ajaxハンドラー
function get_taxonomy_children_ajax() {
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$parent_id || !$taxonomy) {
        wp_send_json_error('パラメータが不正です');
    }
    
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'parent' => $parent_id,
    ));
    
    if (is_wp_error($terms) || empty($terms)) {
        wp_send_json_error('子タームが見つかりませんでした');
    }
    
    $result = array();
    foreach ($terms as $term) {
        $result[] = array(
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        );
    }
    
    wp_send_json_success($result);
}
add_action('wp_ajax_get_taxonomy_children', 'get_taxonomy_children_ajax');
add_action('wp_ajax_nopriv_get_taxonomy_children', 'get_taxonomy_children_ajax');

// タームリンク取得用Ajaxハンドラー
function get_term_link_ajax() {
    $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$term_id || !$taxonomy) {
        wp_send_json_error('パラメータが不正です');
    }
    
    $term = get_term($term_id, $taxonomy);
    
    if (is_wp_error($term) || empty($term)) {
        wp_send_json_error('タームが見つかりませんでした');
    }
    
    $link = get_term_link($term);
    
    if (is_wp_error($link)) {
        wp_send_json_error('リンクの取得に失敗しました');
    }
    
    wp_send_json_success($link);
}
add_action('wp_ajax_get_term_link', 'get_term_link_ajax');
add_action('wp_ajax_nopriv_get_term_link', 'get_term_link_ajax');

// スラッグからタームリンク取得用Ajaxハンドラー
function get_term_link_by_slug_ajax() {
    $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$slug || !$taxonomy) {
        wp_send_json_error('パラメータが不正です');
    }
    
    $term = get_term_by('slug', $slug, $taxonomy);
    
    if (!$term || is_wp_error($term)) {
        wp_send_json_error('タームが見つかりませんでした');
    }
    
    $link = get_term_link($term);
    
    if (is_wp_error($link)) {
        wp_send_json_error('リンクの取得に失敗しました');
    }
    
    wp_send_json_success($link);
}
add_action('wp_ajax_get_term_link_by_slug', 'get_term_link_by_slug_ajax');
add_action('wp_ajax_nopriv_get_term_link_by_slug', 'get_term_link_by_slug_ajax');


/* ------------------------------------------------------------------------------ 
	親カテゴリー・親タームを選択できないようにする
------------------------------------------------------------------------------ */
require_once(ABSPATH . '/wp-admin/includes/template.php');
class Nocheck_Category_Checklist extends Walker_Category_Checklist {

  function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
    extract($args);
    if ( empty( $taxonomy ) )
      $taxonomy = 'category';

    if ( $taxonomy == 'category' )
      $name = 'post_category';
    else
      $name = 'tax_input['.$taxonomy.']';

    $class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
    $cat_child = get_term_children( $category->term_id, $taxonomy );

    if( !empty( $cat_child ) ) {
      $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->slug . '" type="checkbox" name="'.$name.'[]" id="in-'.$taxonomy.'-' . $category->term_id . '"' . checked( in_array( $category->slug, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), true, false ) . ' /> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
    } else {
      $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->slug . '" type="checkbox" name="'.$name.'[]" id="in-'.$taxonomy.'-' . $category->term_id . '"' . checked( in_array( $category->slug, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' /> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
    }
  }

}

/**
 * 求人検索のパスURLを処理するための関数
 */

/**
 * カスタムリライトルールを追加
 */
function job_search_rewrite_rules() {
    // 特徴のみのクエリパラメータ対応
    add_rewrite_rule(
        'jobs/features/?$',
        'index.php?post_type=job&job_features_only=1',
        'top'
    );
    
    // /jobs/location/tokyo/ のようなURLルール
    add_rewrite_rule(
        'jobs/location/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]',
        'top'
    );
    
    // /jobs/position/nurse/ のようなURLルール
    add_rewrite_rule(
        'jobs/position/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]',
        'top'
    );
    
    // /jobs/type/full-time/ のようなURLルール
    add_rewrite_rule(
        'jobs/type/([^/]+)/?$',
        'index.php?post_type=job&job_type=$matches[1]',
        'top'
    );
    
    // /jobs/facility/hospital/ のようなURLルール
    add_rewrite_rule(
        'jobs/facility/([^/]+)/?$',
        'index.php?post_type=job&facility_type=$matches[1]',
        'top'
    );
    
    // /jobs/feature/high-salary/ のようなURLルール
    add_rewrite_rule(
        'jobs/feature/([^/]+)/?$',
        'index.php?post_type=job&job_feature=$matches[1]',
        'top'
    );
    
    // 複合条件のURLルール
    
    // エリア + 職種
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]',
        'top'
    );
    
    // エリア + 雇用形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/type/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_type=$matches[2]',
        'top'
    );
    
    // エリア + 施設形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&facility_type=$matches[2]',
        'top'
    );
    
    // エリア + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_feature=$matches[2]',
        'top'
    );
    
    // 職種 + 雇用形態
    add_rewrite_rule(
        'jobs/position/([^/]+)/type/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&job_type=$matches[2]',
        'top'
    );
    
    // 職種 + 施設形態
    add_rewrite_rule(
        'jobs/position/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&facility_type=$matches[2]',
        'top'
    );
    
    // 職種 + 特徴
    add_rewrite_rule(
        'jobs/position/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&job_feature=$matches[2]',
        'top'
    );
    
    // 三つの条件の組み合わせ
    
    // エリア + 職種 + 雇用形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]',
        'top'
    );
    
    // エリア + 職種 + 施設形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&facility_type=$matches[3]',
        'top'
    );
    
    // エリア + 職種 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_feature=$matches[3]',
        'top'
    );
    
    // 追加: 四つの条件の組み合わせ
    
    // エリア + 職種 + 雇用形態 + 施設形態
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/facility/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]&facility_type=$matches[4]',
        'top'
    );
    
    // エリア + 職種 + 雇用形態 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // エリア + 職種 + 施設形態 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&facility_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // エリア + 雇用形態 + 施設形態 + 特徴
    add_rewrite_rule(
        'jobs/location/([^/]+)/type/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_type=$matches[2]&facility_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // 職種 + 雇用形態 + 施設形態 + 特徴
    add_rewrite_rule(
        'jobs/position/([^/]+)/type/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]&job_type=$matches[2]&facility_type=$matches[3]&job_feature=$matches[4]',
        'top'
    );
    
    // 追加: 五つの条件の組み合わせ（全条件）
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/type/([^/]+)/facility/([^/]+)/feature/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&job_type=$matches[3]&facility_type=$matches[4]&job_feature=$matches[5]',
        'top'
    );
    
    // ページネーション対応（例：エリア + 職種の場合）
    add_rewrite_rule(
        'jobs/location/([^/]+)/position/([^/]+)/page/([0-9]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]&job_position=$matches[2]&paged=$matches[3]',
        'top'
    );
    
    // 他のページネーションパターンも必要に応じて追加
}
add_action('init', 'job_search_rewrite_rules');

/**
 * クエリ変数を追加
 */
function job_search_query_vars($vars) {
    $vars[] = 'job_location';
    $vars[] = 'job_position';
    $vars[] = 'job_type';
    $vars[] = 'facility_type';
    $vars[] = 'job_feature';
    $vars[] = 'job_features_only'; // 追加: 特徴のみの検索フラグ
    return $vars;
}
add_filter('query_vars', 'job_search_query_vars');

/**
 * URLパスとクエリパラメータを解析してフィルター条件を取得する関数
 */
function get_job_filters_from_url() {
    $filters = array();
    
    // 特徴のみのフラグをチェック
    $features_only = get_query_var('job_features_only');
    if (!empty($features_only)) {
        $filters['features_only'] = true;
    }
    
    // パス型URLからの条件取得
    $location = get_query_var('job_location');
    if (!empty($location)) {
        $filters['location'] = $location;
    }
    
    $position = get_query_var('job_position');
    if (!empty($position)) {
        $filters['position'] = $position;
    }
    
    $job_type = get_query_var('job_type');
    if (!empty($job_type)) {
        $filters['type'] = $job_type;
    }
    
    $facility_type = get_query_var('facility_type');
    if (!empty($facility_type)) {
        $filters['facility'] = $facility_type;
    }
    
    // 単一の特徴（パス型URL用）
    $job_feature = get_query_var('job_feature');
    if (!empty($job_feature)) {
        $filters['feature'] = $job_feature;
    }
    
    // クエリパラメータからの複数特徴取得
    if (isset($_GET['features']) && is_array($_GET['features'])) {
        $filters['features'] = array_map('sanitize_text_field', $_GET['features']);
    }
    
    return $filters;
}

/**
 * 特定の特徴フィルターのみを削除した場合のURLを生成する関数
 */
function remove_feature_from_url($feature_to_remove) {
    // 現在のクエリ変数を取得
    $location_slug = get_query_var('job_location');
    $position_slug = get_query_var('job_position');
    $job_type_slug = get_query_var('job_type');
    $facility_type_slug = get_query_var('facility_type');
    $job_feature_slug = get_query_var('job_feature');
    
    // URLクエリパラメータから特徴の配列を取得（複数選択の場合）
    $feature_slugs = isset($_GET['features']) ? (array)$_GET['features'] : array();
    
    // 特徴のスラッグが単一で指定されている場合、それも追加
    if (!empty($job_feature_slug) && !in_array($job_feature_slug, $feature_slugs)) {
        $feature_slugs[] = $job_feature_slug;
    }
    
    // 削除する特徴を配列から除外
    if (!empty($feature_slugs)) {
        $feature_slugs = array_values(array_diff($feature_slugs, array($feature_to_remove)));
    }
    
    // 単一特徴のパラメータが一致する場合、それも削除
    if ($job_feature_slug === $feature_to_remove) {
        $job_feature_slug = '';
    }
    
    // 残りのフィルターでURLを構築
    $url_parts = array();
    $query_params = array();
    
    if (!empty($location_slug)) {
        $url_parts[] = 'location/' . $location_slug;
    }
    
    if (!empty($position_slug)) {
        $url_parts[] = 'position/' . $position_slug;
    }
    
    if (!empty($job_type_slug)) {
        $url_parts[] = 'type/' . $job_type_slug;
    }
    
    if (!empty($facility_type_slug)) {
        $url_parts[] = 'facility/' . $facility_type_slug;
    }
    
    if (!empty($job_feature_slug)) {
        $url_parts[] = 'feature/' . $job_feature_slug;
    }
    
    // URLの構築
    $base_url = home_url('/jobs/');
    
    if (!empty($url_parts)) {
        $path = implode('/', $url_parts);
        $base_url .= $path . '/';
    } else if (!empty($feature_slugs)) {
        // 他の条件がなく特徴のみが残っている場合は特徴専用エンドポイントを使う
        $base_url .= 'features/';
    } else {
        // すべての条件が削除された場合は求人一覧ページに戻る
        return home_url('/jobs/');
    }
    
    // 複数特徴はクエリパラメータとして追加
    if (!empty($feature_slugs)) {
        foreach ($feature_slugs as $feature) {
            $query_params[] = 'features[]=' . urlencode($feature);
        }
    }
    
    // クエリパラメータの追加
    if (!empty($query_params)) {
        $base_url .= '?' . implode('&', $query_params);
    }
    
    return $base_url;
}

/**
 * 特定のフィルターを削除した場合のURLを生成する関数
 */
function remove_filter_from_url($filter_to_remove) {
    // 現在のクエリ変数を取得
    $location_slug = get_query_var('job_location');
    $position_slug = get_query_var('job_position');
    $job_type_slug = get_query_var('job_type');
    $facility_type_slug = get_query_var('facility_type');
    $job_feature_slug = get_query_var('job_feature');
    
    // URLクエリパラメータから特徴の配列を取得（複数選択の場合）
    $feature_slugs = isset($_GET['features']) ? (array)$_GET['features'] : array();
    
    // 特徴のスラッグが単一で指定されている場合、それも追加
    if (!empty($job_feature_slug) && !in_array($job_feature_slug, $feature_slugs)) {
        $feature_slugs[] = $job_feature_slug;
    }
    
    // 削除するフィルターを処理 - 指定されたフィルターのみを空にする
    switch ($filter_to_remove) {
        case 'location':
            $location_slug = '';
            break;
        case 'position':
            $position_slug = '';
            break;
        case 'type':
            $job_type_slug = '';
            break;
        case 'facility':
            $facility_type_slug = '';
            break;
        case 'feature':
            // 特徴フィルターのみを削除
            $job_feature_slug = '';
            $feature_slugs = array();
            break;
    }
    
    // 残りのフィルターでURLを構築
    $url_parts = array();
    $query_params = array();
    
    // 各フィルターが空でなければURLパーツに追加
    if (!empty($location_slug)) {
        $url_parts[] = 'location/' . $location_slug;
    }
    
    if (!empty($position_slug)) {
        $url_parts[] = 'position/' . $position_slug;
    }
    
    if (!empty($job_type_slug)) {
        $url_parts[] = 'type/' . $job_type_slug;
    }
    
    if (!empty($facility_type_slug)) {
        $url_parts[] = 'facility/' . $facility_type_slug;
    }
    
    if (!empty($job_feature_slug)) {
        $url_parts[] = 'feature/' . $job_feature_slug;
    }
    
    // URLの構築
    $base_url = home_url('/jobs/');
    
    // パスがある場合はそれを追加
    if (!empty($url_parts)) {
        $path = implode('/', $url_parts);
        $base_url .= $path . '/';
    } else if (!empty($feature_slugs)) {
        // 他の条件がなく特徴のみが残っている場合は特徴専用エンドポイントを使う
        $base_url .= 'features/';
    } else {
        // すべての条件が削除された場合は求人一覧ページに戻る
        return home_url('/jobs/');
    }
    
    // 複数特徴はクエリパラメータとして追加
    if (!empty($feature_slugs) && $filter_to_remove !== 'feature') {
        foreach ($feature_slugs as $feature) {
            $query_params[] = 'features[]=' . urlencode($feature);
        }
    }
    
    // クエリパラメータの追加
    if (!empty($query_params)) {
        $base_url .= '?' . implode('&', $query_params);
    }
    
    return $base_url;
}

/**
 * 求人アーカイブページのメインクエリを変更する
 */
function modify_job_archive_query($query) {
    // メインクエリのみに適用
    if (!is_admin() && $query->is_main_query() && 
        (is_post_type_archive('job') || 
        is_tax('job_location') || 
        is_tax('job_position') || 
        is_tax('job_type') || 
        is_tax('facility_type') || 
        is_tax('job_feature'))) {
        
        // URLクエリパラメータから特徴の配列を取得（複数選択の場合）
        $feature_slugs = isset($_GET['features']) && is_array($_GET['features']) ? $_GET['features'] : array();
        
        // 特徴（job_feature）のパラメータがある場合のみ処理
        if (!empty($feature_slugs)) {
            // 既存のtax_queryを取得（なければ新規作成）
            $tax_query = $query->get('tax_query');
            
            if (!is_array($tax_query)) {
                $tax_query = array();
            }
            
            // 特徴の条件を追加
            $tax_query[] = array(
                'taxonomy' => 'job_feature',
                'field'    => 'slug',
                'terms'    => $feature_slugs,
                'operator' => 'IN',
            );
            
            // 複数の条件がある場合はAND条件で結合
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            
            // 更新したtax_queryを設定
            $query->set('tax_query', $tax_query);
        }
        
        // 特徴のみのフラグがある場合（/jobs/features/ エンドポイント）
        if (get_query_var('job_features_only')) {
            // この場合、クエリパラメータの特徴のみでフィルタリング
            if (!empty($feature_slugs)) {
                $tax_query = array(
                    array(
                        'taxonomy' => 'job_feature',
                        'field'    => 'slug',
                        'terms'    => $feature_slugs,
                        'operator' => 'IN',
                    )
                );
                
                $query->set('tax_query', $tax_query);
            }
        }
    }
}
add_action('pre_get_posts', 'modify_job_archive_query');

/**
 * タクソノミーの子ターム取得用AJAX処理 (Nonce検証追加版)
 */
function get_taxonomy_children_callback() {
    // Nonce 検証
    check_ajax_referer('get_taxonomy_children', '_wpnonce'); // JavaScript側とアクション名を合わせる

    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    
    if (!$parent_id || !$taxonomy) {
        wp_send_json_error(array('message' => 'パラメータが不正です (parent_id or taxonomy missing)'));
        wp_die();
    }
    
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'parent' => $parent_id,
    ));
    
    if (is_wp_error($terms)) {
        wp_send_json_error(array('message' => 'タームの取得に失敗しました: ' . $terms->get_error_message()));
        wp_die();
    }
    
    if (empty($terms)) {
        wp_send_json_success(array()); // 子タームがない場合は空の成功レスポンスを返す
        wp_die();
    }
    
    $result = array();
    foreach ($terms as $term) {
        $result[] = array(
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        );
    }
    
    wp_send_json_success($result);
    wp_die(); // 忘れずに
}

/**
 * タームのURLを取得するAJAX処理
 */
function get_term_link_callback() {
    // セキュリティチェック
    if (!isset($_POST['term_id']) || !isset($_POST['taxonomy'])) {
        wp_send_json_error('Invalid request');
    }
    
    $term_id = intval($_POST['term_id']);
    $taxonomy = sanitize_text_field($_POST['taxonomy']);
    
    $term = get_term($term_id, $taxonomy);
    if (is_wp_error($term)) {
        wp_send_json_error($term->get_error_message());
    }
    
    $term_link = get_term_link($term);
    if (is_wp_error($term_link)) {
        wp_send_json_error($term_link->get_error_message());
    }
    
    wp_send_json_success($term_link);
}
add_action('wp_ajax_get_term_link', 'get_term_link_callback');
add_action('wp_ajax_nopriv_get_term_link', 'get_term_link_callback');

/**
 * スラッグからタームリンクを取得するAJAX処理
 */
function get_term_link_by_slug_callback() {
    // セキュリティチェック
    if (!isset($_POST['slug']) || !isset($_POST['taxonomy'])) {
        wp_send_json_error('Invalid request');
    }
    
    $slug = sanitize_text_field($_POST['slug']);
    $taxonomy = sanitize_text_field($_POST['taxonomy']);
    
    $term = get_term_by('slug', $slug, $taxonomy);
    if (!$term || is_wp_error($term)) {
        wp_send_json_error('Term not found');
    }
    
    $term_link = get_term_link($term);
    if (is_wp_error($term_link)) {
        wp_send_json_error($term_link->get_error_message());
    }
    
    wp_send_json_success($term_link);
}
add_action('wp_ajax_get_term_link_by_slug', 'get_term_link_by_slug_callback');
add_action('wp_ajax_nopriv_get_term_link_by_slug', 'get_term_link_by_slug_callback');
/**
 * スラッグからタームIDと名前を取得するAJAX処理
 */
function my_ajax_get_term_id_by_slug_callback() {
    // Nonce 検証
    check_ajax_referer('get_term_id_by_slug_nonce', '_wpnonce'); 

    $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
    $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';

    if (empty($taxonomy) || empty($slug)) {
        wp_send_json_error(array('message' => 'パラメータが不正です (taxonomy or slug missing)'));
        wp_die();
    }

    $term = get_term_by('slug', $slug, $taxonomy);

    if ($term && !is_wp_error($term)) {
        wp_send_json_success(array('term_id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug));
    } else {
        wp_send_json_error(array('message' => 'タームが見つかりませんでした (slug: ' . $slug . ', taxonomy: ' . $taxonomy . ')'));
    }
    wp_die(); // 忘れずに
}
add_action('wp_ajax_get_term_id_by_slug', 'my_ajax_get_term_id_by_slug_callback');
add_action('wp_ajax_nopriv_get_term_id_by_slug', 'my_ajax_get_term_id_by_slug_callback'); // 必要に応じてnoprivも

/**
 * URLが変更されたときにリライトルールをフラッシュする
 */
function flush_rewrite_rules_on_theme_activation() {
    if (get_option('job_search_rewrite_rules_flushed') != '1') {
        flush_rewrite_rules();
        update_option('job_search_rewrite_rules_flushed', '1');
    }
}
add_action('after_switch_theme', 'flush_rewrite_rules_on_theme_activation');

// リライトルールの強制フラッシュと再登録
function force_rewrite_rules_refresh() {
    // 初回読み込み時にのみ実行
    if (!get_option('force_rewrite_refresh_done')) {
        // リライトルールを追加
        job_search_rewrite_rules();
        
        // リライトルールをフラッシュ
        flush_rewrite_rules();
        
        // 実行済みフラグを設定
        update_option('force_rewrite_refresh_done', '1');
    }
}
add_action('init', 'force_rewrite_rules_refresh', 99);

// 特徴のみのリライトルールを追加した後にフラッシュする
function flush_features_rewrite_rules() {
    if (!get_option('job_features_rewrite_flushed')) {
        flush_rewrite_rules();
        update_option('job_features_rewrite_flushed', true);
    }
}
add_action('init', 'flush_features_rewrite_rules', 999);

// リライトルールのデバッグ（必要に応じて）
function debug_rewrite_rules() {
    if (current_user_can('manage_options') && isset($_GET['debug_rewrite'])) {
        global $wp_rewrite;
        echo '<pre>';
        print_r($wp_rewrite->rules);
        echo '</pre>';
        exit;
    }
}
add_action('init', 'debug_rewrite_rules', 100);

// 以下のコードがfunctions.phpに追加されているか確認してください
function job_path_query_vars($vars) {
    $vars[] = 'job_path';
    return $vars;
}
add_filter('query_vars', 'job_path_query_vars');

// 求人ステータス変更・削除用のアクション処理
add_action('admin_post_draft_job', 'set_job_to_draft');
add_action('admin_post_publish_job', 'set_job_to_publish');
add_action('admin_post_delete_job', 'delete_job_post');

/**
 * 求人ステータス変更・削除用のアクション処理の修正版
 */
function set_job_to_draft() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'draft_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック - 加盟教室ユーザー用に修正
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_die('この求人が見つかりません。');
    }
    
    // agencyユーザーと管理者の両方に権限を与える
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $is_agency = in_array('agency', (array)$current_user->roles);
    
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_die('この求人を編集する権限がありません。');
    }
    
    // 下書きに変更
    wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'draft'
    ));
    
    // リダイレクト
    wp_redirect(home_url('/job-list/?status=drafted'));
    exit;
}

/**
 * 求人を公開に変更 - 修正版
 */
function set_job_to_publish() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'publish_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック - 加盟教室ユーザー用に修正
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_die('この求人が見つかりません。');
    }
    
    // agencyユーザーと管理者の両方に権限を与える
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $is_agency = in_array('agency', (array)$current_user->roles);
    
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_die('この求人を編集する権限がありません。');
    }
    
    // 公開に変更
    wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'publish'
    ));
    
    // リダイレクト
    wp_redirect(home_url('/job-list/?status=published'));
    exit;
}

/**
 * 求人を削除 - 修正版
 */
function delete_job_post() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック - 加盟教室ユーザー用に修正
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_die('この求人が見つかりません。');
    }
    
    // agencyユーザーと管理者の両方に権限を与える
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $is_agency = in_array('agency', (array)$current_user->roles);
    
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_die('この求人を削除する権限がありません。');
    }
    
    // 削除
    wp_trash_post($job_id);
    
    // リダイレクト
    wp_redirect(home_url('/job-list/?status=deleted'));
    exit;
}
/**
 * 加盟教室(agency)ロールに必要な権限を追加
 */
function add_capabilities_to_agency_role() {
    // agency ロールを取得
    $role = get_role('agency');
    
    if ($role) {
        // 編集・削除関連の権限を追加
        $role->add_cap('edit_posts', true);
        $role->add_cap('delete_posts', true);
        $role->add_cap('publish_posts', true);
        $role->add_cap('edit_published_posts', true);
        $role->add_cap('delete_published_posts', true);
        
        // job カスタム投稿タイプ用の権限
        $role->add_cap('edit_job', true);
        $role->add_cap('read_job', true);
        $role->add_cap('delete_job', true);
        $role->add_cap('edit_jobs', true);
        $role->add_cap('edit_others_jobs', false); // 他のユーザーの投稿は編集不可
        $role->add_cap('publish_jobs', true);
        $role->add_cap('read_private_jobs', false); // プライベート投稿は読み取り不可
        $role->add_cap('edit_published_jobs', true);
        $role->add_cap('delete_published_jobs', true);
    }
}
add_action('init', 'add_capabilities_to_agency_role', 10);
/**
 * 求人用カスタムフィールドとメタボックスの設定
 */

/**
 * 求人投稿のメタボックスを追加
 */
function add_job_meta_boxes() {
    add_meta_box(
        'job_details',
        '求人詳細情報',
        'render_job_details_meta_box',
        'job',
        'normal',
        'high'
    );
    
    add_meta_box(
        'facility_details',
        '施設情報',
        'render_facility_details_meta_box',
        'job',
        'normal',
        'high'
    );
    
    add_meta_box(
        'workplace_environment',
        '職場環境',
        'render_workplace_environment_meta_box',
        'job',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_job_meta_boxes');

/**
 * 求人詳細情報のメタボックスをレンダリング
 */
function render_job_details_meta_box($post) {
    // nonce フィールドを作成
    wp_nonce_field('save_job_details', 'job_details_nonce');
    
    // 現在のカスタムフィールド値を取得
    $salary_range = get_post_meta($post->ID, 'salary_range', true);
    $working_hours = get_post_meta($post->ID, 'working_hours', true);
    $holidays = get_post_meta($post->ID, 'holidays', true);
    $benefits = get_post_meta($post->ID, 'benefits', true);
    $requirements = get_post_meta($post->ID, 'requirements', true);
    $application_process = get_post_meta($post->ID, 'application_process', true);
    $contact_info = get_post_meta($post->ID, 'contact_info', true);
    $bonus_raise = get_post_meta($post->ID, 'bonus_raise', true);
    
    // フォームを表示
    ?>
    <style>
        .job-form-row {
            margin-bottom: 15px;
        }
        .job-form-row label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .job-form-row input[type="text"],
        .job-form-row textarea {
            width: 100%;
        }
        .required {
            color: #f00;
        }
    </style>
    
    <div class="job-form-row">
        <label for="salary_range">給与範囲 <span class="required">*</span></label>
        <input type="text" id="salary_range" name="salary_range" value="<?php echo esc_attr($salary_range); ?>" required>
        <p class="description">例: 月給180,000円〜250,000円</p>
    </div>
    
    <div class="job-form-row">
        <label for="working_hours">勤務時間 <span class="required">*</span></label>
        <input type="text" id="working_hours" name="working_hours" value="<?php echo esc_attr($working_hours); ?>" required>
        <p class="description">例: 9:00〜18:00（休憩60分）</p>
    </div>
    
    <div class="job-form-row">
        <label for="holidays">休日・休暇 <span class="required">*</span></label>
        <input type="text" id="holidays" name="holidays" value="<?php echo esc_attr($holidays); ?>" required>
        <p class="description">例: 土日祝、年末年始、有給休暇あり</p>
    </div>
    
    <div class="job-form-row">
        <label for="benefits">福利厚生</label>
        <textarea id="benefits" name="benefits" rows="4"><?php echo esc_textarea($benefits); ?></textarea>
        <p class="description">社会保険、交通費支給、各種手当など</p>
    </div>
    
    <div class="job-form-row">
        <label for="bonus_raise">昇給・賞与</label>
        <textarea id="bonus_raise" name="bonus_raise" rows="4"><?php echo esc_textarea($bonus_raise); ?></textarea>
        <p class="description">昇給制度や賞与の詳細など</p>
    </div>
    
    <div class="job-form-row">
        <label for="requirements">応募要件</label>
        <textarea id="requirements" name="requirements" rows="4"><?php echo esc_textarea($requirements); ?></textarea>
        <p class="description">必要な資格や経験など</p>
    </div>
    <div class="job-form-row">
        <label for="contact_info">仕事内容 <span class="required">*</span></label>
        <textarea id="contact_info" name="contact_info" rows="4" required><?php echo esc_textarea($contact_info); ?></textarea>
        <p class="description">電話番号、メールアドレス、応募フォームURLなど</p>
    </div>
    <div class="job-form-row">
        <label for="application_process">選考プロセス</label>
        <textarea id="application_process" name="application_process" rows="4"><?php echo esc_textarea($application_process); ?></textarea>
        <p class="description">書類選考、面接回数など</p>
    </div>
    
    <?php
}

/**
 * 施設情報のメタボックスをレンダリング
 */
function render_facility_details_meta_box($post) {
    // nonce フィールドを作成
    wp_nonce_field('save_facility_details', 'facility_details_nonce');
    
    // 現在のカスタムフィールド値を取得
    $facility_name = get_post_meta($post->ID, 'facility_name', true);
    $facility_address = get_post_meta($post->ID, 'facility_address', true);
    $facility_tel = get_post_meta($post->ID, 'facility_tel', true);
    $facility_hours = get_post_meta($post->ID, 'facility_hours', true);
    $facility_url = get_post_meta($post->ID, 'facility_url', true);
    $facility_company = get_post_meta($post->ID, 'facility_company', true);
    $capacity = get_post_meta($post->ID, 'capacity', true);
    $staff_composition = get_post_meta($post->ID, 'staff_composition', true);
    $company_url = get_post_meta($post->ID, 'company_url', true);
    $capacity = get_post_meta($post->ID, 'capacity', true);
    $staff_composition = get_post_meta($post->ID, 'staff_composition', true);
    // フォームを表示
    ?>
    <div class="job-form-row">
        <label for="facility_name">施設名 <span class="required">*</span></label>
        <input type="text" id="facility_name" name="facility_name" value="<?php echo esc_attr($facility_name); ?>" required>
    </div>
    
    <div class="job-form-row">
        <label for="facility_company">運営会社名</label>
        <input type="text" id="facility_company" name="facility_company" value="<?php echo esc_attr($facility_company); ?>">
    </div>
    <div class="job-form-row">
        <label for="facility_company">運営会社のWebサイトURL</label>
        <input type="text" id="company_url" name="company_url" value="<?php echo esc_attr($company_url); ?>">
    </div>
    <div class="job-form-row">
        <label for="facility_address">施設住所 <span class="required">*</span></label>
        <input type="text" id="facility_address" name="facility_address" value="<?php echo esc_attr($facility_address); ?>" required>
        <p class="description">例: 〒123-4567 神奈川県横浜市○○区△△町1-2-3</p>
    </div>
    
    <div class="job-form-row">
        <label for="capacity">利用者定員数</label>
        <input type="text" id="capacity" name="capacity" value="<?php echo esc_attr($capacity); ?>">
        <p class="description">例: 60名（0〜5歳児）</p>
    </div>
    
    <div class="job-form-row">
        <label for="staff_composition">スタッフ構成</label>
        <textarea id="staff_composition" name="staff_composition" rows="4"><?php echo esc_textarea($staff_composition); ?></textarea>
        <p class="description">例: 園長1名、主任保育士2名、保育士12名、栄養士2名、調理員3名、事務員1名</p>
    </div>
    
    <div class="job-form-row">
        <label for="facility_tel">施設電話番号</label>
        <input type="text" id="facility_tel" name="facility_tel" value="<?php echo esc_attr($facility_tel); ?>">
    </div>
    
    <div class="job-form-row">
        <label for="facility_hours">施設営業時間</label>
        <input type="text" id="facility_hours" name="facility_hours" value="<?php echo esc_attr($facility_hours); ?>">
    </div>
    
    <div class="job-form-row">
        <label for="facility_url">施設WebサイトURL</label>
        <input type="url" id="facility_url" name="facility_url" value="<?php echo esc_url($facility_url); ?>">
    </div>
    <?php
}

/**
 * 職場環境のメタボックスをレンダリング - 更新版
 */
function render_workplace_environment_meta_box($post) {
    // nonce フィールドを作成
    wp_nonce_field('save_workplace_environment', 'workplace_environment_nonce');
    
    // 既存のデータを取得
    $daily_schedule = get_post_meta($post->ID, 'daily_schedule', true);
    $staff_voices = get_post_meta($post->ID, 'staff_voices', true);
    
    // 新形式のデータ
    $daily_schedule_items = get_post_meta($post->ID, 'daily_schedule_items', true);
    $staff_voice_items = get_post_meta($post->ID, 'staff_voice_items', true);
    
    // JavaScript とスタイルを追加
    ?>
    <style>
    .schedule-items, .voice-items {
        margin-bottom: 15px;
    }
    .schedule-item, .voice-item {
        border: 1px solid #ddd;
        padding: 10px;
        margin-bottom: 10px;
        background: #f9f9f9;
        position: relative;
    }
    .schedule-row, .voice-row {
        margin-bottom: 10px;
    }
    .remove-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        color: red;
        cursor: pointer;
    }
    .image-preview {
        max-width: 100px;
        max-height: 100px;
        margin-top: 5px;
    }
    </style>
    
    <!-- 旧フォーマットのフィールド（バックアップとして保持） -->
    <div style="display: none;">
        <div class="job-form-row">
            <label for="daily_schedule">一日の流れ（旧形式）</label>
            <textarea id="daily_schedule" name="daily_schedule" rows="8"><?php echo esc_textarea($daily_schedule); ?></textarea>
        </div>
        
        <div class="job-form-row">
            <label for="staff_voices">職員の声（旧形式）</label>
            <textarea id="staff_voices" name="staff_voices" rows="8"><?php echo esc_textarea($staff_voices); ?></textarea>
        </div>
    </div>
    
    <!-- 新フォーマットの一日の流れ -->
    <div class="workplace-section">
        <h4>仕事の一日の流れ</h4>
        <div id="schedule-container" class="schedule-items">
            <?php
            if (is_array($daily_schedule_items) && !empty($daily_schedule_items)) {
                foreach ($daily_schedule_items as $index => $item) {
                    ?>
                    <div class="schedule-item">
                        <span class="remove-btn" onclick="removeScheduleItem(this)">✕</span>
                        <div class="schedule-row">
                            <label>時間:</label>
                            <input type="text" name="daily_schedule_time[]" value="<?php echo esc_attr($item['time']); ?>" placeholder="9:00" style="width: 100px;">
                        </div>
                        <div class="schedule-row">
                            <label>タイトル:</label>
                            <input type="text" name="daily_schedule_title[]" value="<?php echo esc_attr($item['title']); ?>" placeholder="出社・朝礼" style="width: 250px;">
                        </div>
                        <div class="schedule-row">
                            <label>詳細:</label>
                            <textarea name="daily_schedule_description[]" rows="3" style="width: 100%;"><?php echo esc_textarea($item['description']); ?></textarea>
                        </div>
                    </div>
                    <?php
                }
            } else {
                // 空のテンプレート
                ?>
                <div class="schedule-item">
                    <span class="remove-btn" onclick="removeScheduleItem(this)">✕</span>
                    <div class="schedule-row">
                        <label>時間:</label>
                        <input type="text" name="daily_schedule_time[]" placeholder="9:00" style="width: 100px;">
                    </div>
                    <div class="schedule-row">
                        <label>タイトル:</label>
                        <input type="text" name="daily_schedule_title[]" placeholder="出社・朝礼" style="width: 250px;">
                    </div>
                    <div class="schedule-row">
                        <label>詳細:</label>
                        <textarea name="daily_schedule_description[]" rows="3" style="width: 100%;"></textarea>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <button type="button" class="button" onclick="addScheduleItem()">時間枠を追加</button>
    </div>
    
    <!-- 新フォーマットの職員の声 -->
    <div class="workplace-section" style="margin-top: 20px;">
        <h4>職員の声</h4>
        <div id="voice-container" class="voice-items">
            <?php
            if (is_array($staff_voice_items) && !empty($staff_voice_items)) {
                foreach ($staff_voice_items as $index => $item) {
                    $image_url = '';
                    if (!empty($item['image_id'])) {
                        $image_url = wp_get_attachment_url($item['image_id']);
                    }
                    ?>
                    <div class="voice-item">
                        <span class="remove-btn" onclick="removeVoiceItem(this)">✕</span>
                        <div class="voice-row">
                            <label>サムネイル:</label>
                            <input type="hidden" name="staff_voice_image[]" value="<?php echo esc_attr($item['image_id']); ?>" class="voice-image-id">
                            <button type="button" class="button upload-image" onclick="uploadVoiceImage(this)">画像を選択</button>
                            <button type="button" class="button remove-image" onclick="removeVoiceImage(this)" <?php echo empty($image_url) ? 'style="display:none;"' : ''; ?>>画像を削除</button>
                            <div class="image-preview-container">
                                <?php if (!empty($image_url)): ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="" class="image-preview">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="voice-row">
                            <label>職種:</label>
                            <input type="text" name="staff_voice_role[]" value="<?php echo esc_attr($item['role']); ?>" placeholder="保育士" style="width: 250px;">
                        </div>
                        <div class="voice-row">
                            <label>勤続年数:</label>
                            <input type="text" name="staff_voice_years[]" value="<?php echo esc_attr($item['years']); ?>" placeholder="3年目" style="width: 100px;">
                        </div>
                        <div class="voice-row">
                            <label>コメント:</label>
                            <textarea name="staff_voice_comment[]" rows="4" style="width: 100%;"><?php echo esc_textarea($item['comment']); ?></textarea>
                        </div>
                    </div>
                    <?php
                }
            } else {
                // 空のテンプレート
                ?>
                <div class="voice-item">
                    <span class="remove-btn" onclick="removeVoiceItem(this)">✕</span>
                    <div class="voice-row">
                        <label>サムネイル:</label>
                        <input type="hidden" name="staff_voice_image[]" value="" class="voice-image-id">
                        <button type="button" class="button upload-image" onclick="uploadVoiceImage(this)">画像を選択</button>
                        <button type="button" class="button remove-image" onclick="removeVoiceImage(this)" style="display:none;">画像を削除</button>
                        <div class="image-preview-container"></div>
                    </div>
                    <div class="voice-row">
                        <label>職種:</label>
                        <input type="text" name="staff_voice_role[]" placeholder="保育士" style="width: 250px;">
                    </div>
                    <div class="voice-row">
                        <label>勤続年数:</label>
                        <input type="text" name="staff_voice_years[]" placeholder="3年目" style="width: 100px;">
                    </div>
                    <div class="voice-row">
                        <label>コメント:</label>
                        <textarea name="staff_voice_comment[]" rows="4" style="width: 100%;"></textarea>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <button type="button" class="button" onclick="addVoiceItem()">職員の声を追加</button>
    </div>
    
    <script>
    // 一日の流れを追加
    function addScheduleItem() {
        var template = document.querySelector('#schedule-container .schedule-item:first-child').cloneNode(true);
        // 入力内容をクリア
        template.querySelectorAll('input, textarea').forEach(function(el) {
            el.value = '';
        });
        document.getElementById('schedule-container').appendChild(template);
    }
    
    // 一日の流れを削除
    function removeScheduleItem(button) {
        var container = document.getElementById('schedule-container');
        if (container.children.length > 1) {
            button.parentNode.remove();
        } else {
            alert('少なくとも1つの項目が必要です');
        }
    }
    
    // 職員の声を追加
    function addVoiceItem() {
        var template = document.querySelector('#voice-container .voice-item:first-child').cloneNode(true);
        // 入力内容をクリア
        template.querySelectorAll('input, textarea').forEach(function(el) {
            el.value = '';
        });
        template.querySelector('.image-preview-container').innerHTML = '';
        template.querySelector('.remove-image').style.display = 'none';
        document.getElementById('voice-container').appendChild(template);
    }
    
    // 職員の声を削除
    function removeVoiceItem(button) {
        var container = document.getElementById('voice-container');
        if (container.children.length > 1) {
            button.parentNode.remove();
        } else {
            alert('少なくとも1つの項目が必要です');
        }
    }
    
    // 職員画像をアップロード
    function uploadVoiceImage(button) {
        var frame = wp.media({
            title: '職員の声の画像を選択',
            button: {
                text: '画像を選択'
            },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            var container = button.closest('.voice-item');
            var imageId = container.querySelector('.voice-image-id');
            var previewContainer = container.querySelector('.image-preview-container');
            var removeButton = container.querySelector('.remove-image');
            
            imageId.value = attachment.id;
            previewContainer.innerHTML = '<img src="' + attachment.url + '" alt="" class="image-preview">';
            removeButton.style.display = 'inline-block';
        });
        
        frame.open();
    }
    
    // 職員画像を削除
    function removeVoiceImage(button) {
        var container = button.closest('.voice-item');
        var imageId = container.querySelector('.voice-image-id');
        var previewContainer = container.querySelector('.image-preview-container');
        
        imageId.value = '';
        previewContainer.innerHTML = '';
        button.style.display = 'none';
    }
    </script>
    <?php
}

/**
 * 管理画面と前面の編集ページで一貫したデータ構造を使用するための修正
 */
function save_workplace_environment_data($post_id) {
    // すでにカスタムフィールドを保存する関数が実行されている場合は終了
    if (did_action('save_post_' . get_post_type($post_id)) > 1) {
        return;
    }
    
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // nonceチェック
    if (!isset($_POST['workplace_environment_nonce']) || 
        !wp_verify_nonce($_POST['workplace_environment_nonce'], 'save_workplace_environment')) {
        return;
    }
    
    // 旧形式のフィールドも保存（互換性のため）
    if (isset($_POST['daily_schedule'])) {
        update_post_meta($post_id, 'daily_schedule', wp_kses_post($_POST['daily_schedule']));
    }
    
    if (isset($_POST['staff_voices'])) {
        update_post_meta($post_id, 'staff_voices', wp_kses_post($_POST['staff_voices']));
    }
    
    // 新形式の一日の流れデータ（配列形式）
    if (isset($_POST['daily_schedule_time']) && is_array($_POST['daily_schedule_time'])) {
        $schedule_items = array();
        $count = count($_POST['daily_schedule_time']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['daily_schedule_time'][$i])) {
                $schedule_items[] = array(
                    'time' => sanitize_text_field($_POST['daily_schedule_time'][$i]),
                    'title' => sanitize_text_field($_POST['daily_schedule_title'][$i]),
                    'description' => wp_kses_post($_POST['daily_schedule_description'][$i])
                );
            }
        }
        
        update_post_meta($post_id, 'daily_schedule_items', $schedule_items);
    }
    
    // 新形式の職員の声データ（配列形式）
    if (isset($_POST['staff_voice_role']) && is_array($_POST['staff_voice_role'])) {
        $voice_items = array();
        $count = count($_POST['staff_voice_role']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['staff_voice_role'][$i])) {
                $voice_items[] = array(
                    'image_id' => intval($_POST['staff_voice_image'][$i]),
                    'role' => sanitize_text_field($_POST['staff_voice_role'][$i]),
                    'years' => sanitize_text_field($_POST['staff_voice_years'][$i]),
                    'comment' => wp_kses_post($_POST['staff_voice_comment'][$i])
                );
            }
        }
        
        update_post_meta($post_id, 'staff_voice_items', $voice_items);
    }
}
add_action('save_post_job', 'save_workplace_environment_data', 20);

/**
 * 管理画面メディア関連のスクリプト読み込み
 */
function load_admin_media_scripts($hook) {
    global $post;
    
    // 投稿編集画面のみに読み込み
    if ($hook == 'post.php' || $hook == 'post-new.php') {
        if (isset($post) && $post->post_type == 'job') {
            wp_enqueue_media();
        }
    }
}
add_action('admin_enqueue_scripts', 'load_admin_media_scripts');

/**
 * フロントエンドと管理画面の保存処理を統一するためのデータ同期
 */
function sync_workplace_environment_data($post_id) {
    // 通常の保存処理が完了した後に実行
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 該当の投稿タイプのみ処理
    if (get_post_type($post_id) !== 'job') {
        return;
    }
    
    // 新形式のデータが存在するか確認
    $daily_schedule_items = get_post_meta($post_id, 'daily_schedule_items', true);
    $staff_voice_items = get_post_meta($post_id, 'staff_voice_items', true);
    
    // 旧形式のデータを取得
    $daily_schedule = get_post_meta($post_id, 'daily_schedule', true);
    $staff_voices = get_post_meta($post_id, 'staff_voices', true);
    
    // 新形式のデータが存在しない場合、旧形式から変換を試みる
    if (empty($daily_schedule_items) && !empty($daily_schedule)) {
        // 簡易的な変換処理（実際のデータ構造によって調整が必要）
        $schedule_items = array(
            array(
                'time' => '9:00',
                'title' => '業務開始',
                'description' => $daily_schedule
            )
        );
        update_post_meta($post_id, 'daily_schedule_items', $schedule_items);
    }
    
    if (empty($staff_voice_items) && !empty($staff_voices)) {
        // 簡易的な変換処理（実際のデータ構造によって調整が必要）
        $voice_items = array(
            array(
                'image_id' => 0,
                'role' => '職員',
                'years' => '勤続期間',
                'comment' => $staff_voices
            )
        );
        update_post_meta($post_id, 'staff_voice_items', $voice_items);
    }
}
add_action('save_post', 'sync_workplace_environment_data', 30);

/**
 * カスタムフィールドのデータを保存
 */
function save_job_meta_data($post_id) {
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // 求人詳細情報の保存
    if (isset($_POST['job_details_nonce']) && wp_verify_nonce($_POST['job_details_nonce'], 'save_job_details')) {
        if (isset($_POST['salary_range'])) {
            update_post_meta($post_id, 'salary_range', sanitize_text_field($_POST['salary_range']));
        }
        
        if (isset($_POST['working_hours'])) {
            update_post_meta($post_id, 'working_hours', sanitize_text_field($_POST['working_hours']));
        }
        
        if (isset($_POST['holidays'])) {
            update_post_meta($post_id, 'holidays', sanitize_text_field($_POST['holidays']));
        }
        
        if (isset($_POST['benefits'])) {
            update_post_meta($post_id, 'benefits', wp_kses_post($_POST['benefits']));
        }
        
        if (isset($_POST['bonus_raise'])) {
            update_post_meta($post_id, 'bonus_raise', wp_kses_post($_POST['bonus_raise']));
        }
        
        if (isset($_POST['requirements'])) {
            update_post_meta($post_id, 'requirements', wp_kses_post($_POST['requirements']));
        }
        
        if (isset($_POST['application_process'])) {
            update_post_meta($post_id, 'application_process', wp_kses_post($_POST['application_process']));
        }
        
        if (isset($_POST['contact_info'])) {
            update_post_meta($post_id, 'contact_info', wp_kses_post($_POST['contact_info']));
        }
    }
    
    // 施設情報の保存
    if (isset($_POST['facility_details_nonce']) && wp_verify_nonce($_POST['facility_details_nonce'], 'save_facility_details')) {
        if (isset($_POST['facility_name'])) {
            update_post_meta($post_id, 'facility_name', sanitize_text_field($_POST['facility_name']));
        }
        
        if (isset($_POST['facility_company'])) {
            update_post_meta($post_id, 'facility_company', sanitize_text_field($_POST['facility_company']));
        }
        
        if (isset($_POST['facility_address'])) {
            update_post_meta($post_id, 'facility_address', sanitize_text_field($_POST['facility_address']));
        }
        
        if (isset($_POST['capacity'])) {
            update_post_meta($post_id, 'capacity', sanitize_text_field($_POST['capacity']));
        }
        
        if (isset($_POST['staff_composition'])) {
            update_post_meta($post_id, 'staff_composition', wp_kses_post($_POST['staff_composition']));
        }
        
        if (isset($_POST['facility_tel'])) {
            update_post_meta($post_id, 'facility_tel', sanitize_text_field($_POST['facility_tel']));
        }
        
        if (isset($_POST['facility_hours'])) {
            update_post_meta($post_id, 'facility_hours', sanitize_text_field($_POST['facility_hours']));
        }
        
        if (isset($_POST['facility_url'])) {
            update_post_meta($post_id, 'facility_url', esc_url_raw($_POST['facility_url']));
        }
    }
    
    // 職場環境の保存
    if (isset($_POST['workplace_environment_nonce']) && wp_verify_nonce($_POST['workplace_environment_nonce'], 'save_workplace_environment')) {
        if (isset($_POST['daily_schedule'])) {
            update_post_meta($post_id, 'daily_schedule', wp_kses_post($_POST['daily_schedule']));
        }
        
        if (isset($_POST['staff_voices'])) {
            update_post_meta($post_id, 'staff_voices', wp_kses_post($_POST['staff_voices']));
        }
    }
}
add_action('save_post_job', 'save_job_meta_data');

// 追加のカスタムフィールドを設定
function add_additional_job_fields($post_id) {
    // 本文タイトル
    if (isset($_POST['job_content_title'])) {
        update_post_meta($post_id, 'job_content_title', sanitize_text_field($_POST['job_content_title']));
    }
    
    // GoogleMap埋め込みコード
    if (isset($_POST['facility_map'])) {
        update_post_meta($post_id, 'facility_map', wp_kses($_POST['facility_map'], array(
            'iframe' => array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'frameborder' => array(),
                'style' => array(),
                'allowfullscreen' => array()
            )
        )));
    }
    
    // 仕事の一日の流れ（配列形式）
    if (isset($_POST['daily_schedule_time']) && is_array($_POST['daily_schedule_time'])) {
        $schedule_items = array();
        $count = count($_POST['daily_schedule_time']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['daily_schedule_time'][$i])) {
                $schedule_items[] = array(
                    'time' => sanitize_text_field($_POST['daily_schedule_time'][$i]),
                    'title' => sanitize_text_field($_POST['daily_schedule_title'][$i]),
                    'description' => wp_kses_post($_POST['daily_schedule_description'][$i])
                );
            }
        }
        
        update_post_meta($post_id, 'daily_schedule_items', $schedule_items);
    }
    
    // 職員の声（配列形式）
    if (isset($_POST['staff_voice_role']) && is_array($_POST['staff_voice_role'])) {
        $voice_items = array();
        $count = count($_POST['staff_voice_role']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['staff_voice_role'][$i])) {
                $voice_items[] = array(
                    'image_id' => intval($_POST['staff_voice_image'][$i]),
                    'role' => sanitize_text_field($_POST['staff_voice_role'][$i]),
                    'years' => sanitize_text_field($_POST['staff_voice_years'][$i]),
                    'comment' => wp_kses_post($_POST['staff_voice_comment'][$i])
                );
            }
        }
        
        update_post_meta($post_id, 'staff_voice_items', $voice_items);
    }
}

// 求人投稿保存時にカスタムフィールドを処理
add_action('save_post_job', function($post_id) {
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // 追加フィールドを保存
    add_additional_job_fields($post_id);
}, 15);


// JavaScriptとCSSを登録・読み込むための関数
function register_job_search_scripts() {
    // URLパラメータを追加して、キャッシュを防止
    $version = '1.0.0';
    
    // スタイルシートの登録（必要に応じて）
    wp_register_style('job-search-style', get_stylesheet_directory_uri() . '/css/job-search.css', array(), $version);
    wp_enqueue_style('job-search-style');
    
    // JavaScriptの登録
    wp_register_script('job-search', get_stylesheet_directory_uri() . '/js/job-search.js', array('jquery'), $version, true);
    
    // JavaScriptにパラメータを渡す
    wp_localize_script('job-search', 'job_search_params', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'site_url' => home_url(),
        'nonce' => wp_create_nonce('job_search_nonce')
    ));
    
    // JavaScriptを読み込む
    wp_enqueue_script('job-search');
}
add_action('wp_enqueue_scripts', 'register_job_search_scripts');



/**
 * 退会処理の実装
 */

// 退会処理のアクションフックを追加
add_action('admin_post_delete_my_account', 'handle_delete_account');

/**
 * ユーザーアカウント削除処理
 */
function handle_delete_account() {
    // ログインチェック
    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url());
        exit;
    }
    
    // nonceチェック
    if (!isset($_POST['delete_account_nonce']) || !wp_verify_nonce($_POST['delete_account_nonce'], 'delete_account_action')) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 退会確認チェックボックスが選択されているか確認
    if (!isset($_POST['confirm_deletion'])) {
        wp_redirect(add_query_arg('error', 'no_confirmation', home_url('/withdrawal/')));
        exit;
    }
    
    // 現在のユーザー情報を取得
    $current_user = wp_get_current_user();
    $user_email = $current_user->user_email;
    $user_name = $current_user->display_name;
    $user_id = $current_user->ID;
    
    // 退会完了メールを送信
    send_account_deletion_email($user_email, $user_name);
    
    // ユーザーをログアウト
    wp_logout();
    
    // ユーザーアカウントを削除
    // WP-Membersのユーザー削除APIがあれば使用する
    if (function_exists('wpmem_delete_user')) {
        wpmem_delete_user($user_id);
    } else {
        // WP標準のユーザー削除機能を使用
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id);
    }
    
    // 退会完了ページへリダイレクト
    wp_redirect(home_url('/?account_deleted=true'));
    exit;
}

/**
 * 退会完了メールを送信する
 *
 * @param string $user_email 退会するユーザーのメールアドレス
 * @param string $user_name  退会するユーザーの表示名
 */
function send_account_deletion_email($user_email, $user_name) {
    $site_name = get_bloginfo('name');
    $admin_email = get_option('admin_email');
    
    // メールの件名
    $subject = sprintf('[%s] 退会手続き完了のお知らせ', $site_name);
    
    // メールの本文
    $message = sprintf(
        '%s 様
        
退会手続きが完了しました。

%s をご利用いただき、誠にありがとうございました。
アカウント情報および関連データはすべて削除されました。

またのご利用をお待ちしております。

------------------------------
%s
%s',
        $user_name,
        $site_name,
        $site_name,
        home_url()
    );
    
    // メールヘッダー
    $headers = array(
        'From: ' . $site_name . ' <' . $admin_email . '>',
        'Content-Type: text/plain; charset=UTF-8'
    );
    
    // メール送信
    wp_mail($user_email, $subject, $message, $headers);
    
    // 管理者にも通知
    $admin_subject = sprintf('[%s] ユーザー退会通知', $site_name);
    $admin_message = sprintf(
        '以下のユーザーが退会しました:
        
ユーザー名: %s
メールアドレス: %s
退会日時: %s',
        $user_name,
        $user_email,
        current_time('Y-m-d H:i:s')
    );
    
    wp_mail($admin_email, $admin_subject, $admin_message, $headers);
}

/**
 * トップページに退会完了メッセージを表示
 */
function show_account_deleted_message() {
    if (isset($_GET['account_deleted']) && $_GET['account_deleted'] === 'true') {
        echo '<div class="account-deleted-message">';
        echo '<p><strong>退会手続きが完了しました。ご利用ありがとうございました。</strong></p>';
        echo '</div>';
        
        // スタイルを追加
        echo '<style>
        .account-deleted-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border-left: 4px solid #28a745;
        }
        </style>';
    }
}
add_action('wp_body_open', 'show_account_deleted_message');




/**
 * WordPressログイン画面とパスワードリセット画面のカスタマイズ
 */

// ログイン画面に独自のスタイルを適用
add_action('login_enqueue_scripts', 'custom_login_styles');

function custom_login_styles() {
    ?>
    <style type="text/css">
        /* 全体のスタイル */
        body.login {
            background-color: #f8f9fa;
        }
        
        /* WordPressロゴを非表示 */
        #login h1 a {
            display: none;
        }
        
        /* フォーム全体の調整 */
        #login {
            width: 400px;
            padding: 5% 0 0;
        }
        
        /* 見出しを追加 */
        #login:before {
            content: "ログイン";
            display: block;
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        
        /* フォームのスタイル */
        .login form {
            margin-top: 20px;
            padding: 26px 24px 34px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* ラベルとフォーム要素 */
        .login label {
            font-size: 14px;
            color: #333;
            font-weight: bold;
        }
        
        .login form .input {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            margin: 5px 0 15px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        /* ボタンスタイル */
        .login .button-primary {
            background-color: #0073aa;
            border-color: #0073aa;
            color: white;
            width: 100%;
            padding: 10px;
            text-shadow: none;
            box-shadow: none;
            border-radius: 4px;
            font-size: 16px;
            height: auto;
            line-height: normal;
            text-transform: none;
        }
        
        .login .button-primary:hover {
            background-color: #005f8a;
            border-color: #005f8a;
        }
        
        /* リンクのスタイル */
        #nav, #backtoblog {
            text-align: center;
            margin: 16px 0 0;
            font-size: 14px;
        }
        
        #nav a, #backtoblog a {
            color: #0073aa;
            text-decoration: none;
        }
        
        #nav a:hover, #backtoblog a:hover {
            color: #005f8a;
            text-decoration: underline;
        }
        
        /* メッセージスタイル */
        .login .message,
        .login #login_error {
            border-radius: 4px;
        }
        
        /* 余計な要素を非表示 */
        .login .privacy-policy-page-link {
            display: none;
        }
        
        /* パスワード強度インジケータを非表示 */
        .pw-weak {
            display: none !important;
        }
        
        /* パスワードリセット画面専用のスタイル */
        body.login-action-rp form p:first-child,
        body.login-action-resetpass form p:first-child {
            font-size: 14px;
            color: #333;
        }
        
        /* 文言を日本語化（CSSのcontentで置き換え） */
        body.login-action-lostpassword form p:first-child {
            display: none;  /* 元のテキストを非表示 */
        }
        
        body.login-action-lostpassword form:before {
            content: "メールアドレスを入力してください。パスワードリセット用のリンクをメールでお送りします。";
            display: block;
            margin-bottom: 15px;
            font-size: 14px;
            color: #333;
        }
        
        body.login-action-rp form:before,
        body.login-action-resetpass form:before {
            content: "新しいパスワードを設定してください。";
            display: block;
            margin-bottom: 15px;
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
    </style>
    <?php
}

// ログイン画面のタイトルを変更
add_filter('login_title', 'custom_login_title', 10, 2);

function custom_login_title($title, $url) {
    if (isset($_GET['action']) && $_GET['action'] == 'lostpassword') {
        return 'パスワード再設定 | ' . get_bloginfo('name');
    } elseif (isset($_GET['action']) && ($_GET['action'] == 'rp' || $_GET['action'] == 'resetpass')) {
        return '新しいパスワードの設定 | ' . get_bloginfo('name');
    }
    return $title;
}

// ログイン画面のテキストを日本語化
add_filter('gettext', 'custom_login_text', 20, 3);

function custom_login_text($translated_text, $text, $domain) {
    if ($domain == 'default') {
        switch ($text) {
            // パスワードリセット関連
            case 'Enter your username or email address and you will receive a link to create a new password via email.':
                $translated_text = 'メールアドレスを入力してください。パスワードリセット用のリンクをメールでお送りします。';
                break;
            case 'Username or Email Address':
                $translated_text = 'メールアドレス';
                break;
            case 'Get New Password':
                $translated_text = 'パスワード再設定メールを送信';
                break;
            case 'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox. Please wait at least 10 minutes before attempting another reset.':
                $translated_text = 'パスワード再設定用のメールを送信しました。メールが届くまで数分かかる場合があります。10分以上経ってもメールが届かない場合は、再度試してください。';
                break;
            case 'There is no account with that username or email address.':
                $translated_text = '入力されたメールアドレスのアカウントが見つかりません。';
                break;
            
            // パスワード設定画面関連
            case 'Enter your new password below or generate one.':
            case 'Enter your new password below.':
                $translated_text = '新しいパスワードを入力してください。';
                break;
            case 'New password':
                $translated_text = '新しいパスワード';
                break;
            case 'Confirm new password':
                $translated_text = '新しいパスワード（確認）';
                break;
            case 'Reset Password':
                $translated_text = 'パスワードを変更';
                break;
            case 'Your password has been reset. <a href="%s">Log in</a>':
                $translated_text = 'パスワードが変更されました。<a href="%s">ログイン</a>してください。';
                break;
            
            // その他のリンク
            case 'Log in':
                $translated_text = 'ログイン';
                break;
            case '&larr; Back to %s':
                $translated_text = 'トップページに戻る';
                break;
        }
    }
    return $translated_text;
}

// パスワードリセットメールのカスタマイズ
add_filter('retrieve_password_message', 'custom_password_reset_email', 10, 4);
add_filter('retrieve_password_title', 'custom_password_reset_email_title', 10, 1);

function custom_password_reset_email_title($title) {
    $site_name = get_bloginfo('name');
    return '[' . $site_name . '] パスワード再設定のご案内';
}

function custom_password_reset_email($message, $key, $user_login, $user_data) {
    $site_name = get_bloginfo('name');
    
    // リセットURL
    $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
    
    // メール本文
    $message = $user_data->display_name . " 様\r\n\r\n";
    $message .= "パスワード再設定のリクエストを受け付けました。\r\n\r\n";
    $message .= "以下のリンクをクリックして、新しいパスワードを設定してください：\r\n";
    $message .= $reset_url . "\r\n\r\n";
    $message .= "このリンクは24時間のみ有効です。\r\n\r\n";
    $message .= "リクエストに心当たりがない場合は、このメールを無視してください。\r\n\r\n";
    $message .= "------------------------------\r\n";
    $message .= $site_name . "\r\n";
    
    return $message;
}

// パスワード変更後のリダイレクト先を変更
add_action('login_form_resetpass', 'redirect_after_password_reset');

function redirect_after_password_reset() {
    if ('POST' === $_SERVER['REQUEST_METHOD']) {
        add_filter('login_redirect', 'custom_password_reset_redirect', 10, 3);
    }
}

function custom_password_reset_redirect($redirect_to, $requested_redirect_to, $user) {
    return home_url('/login/?password-reset=success');
}

// functions.php に追加
function custom_job_post_link($permalink, $post) {
    if ($post->post_type !== 'job') {
        return $permalink;
    }
    
    // 地域と職種のタクソノミーを取得
    $location_terms = get_the_terms($post->ID, 'job_location');
    $position_terms = get_the_terms($post->ID, 'job_position');
    
    $location_slug = $location_terms && !is_wp_error($location_terms) ? $location_terms[0]->slug : 'area';
    $position_slug = $position_terms && !is_wp_error($position_terms) ? $position_terms[0]->slug : 'position';
    
    // 新しいURLパターンを構築
    $permalink = home_url('/jobs/' . $location_slug . '/' . $position_slug . '/' . $post->ID . '/');
    
    return $permalink;
}
add_filter('post_type_link', 'custom_job_post_link', 10, 2);

// functions.php に追加
function add_custom_job_rewrite_rules() {
    add_rewrite_rule(
        'jobs/([^/]+)/([^/]+)/([0-9]+)/?$',
        'index.php?post_type=job&p=$matches[3]',
        'top'
    );
    
    // 地域別一覧ページ
    add_rewrite_rule(
        'jobs/([^/]+)/?$',
        'index.php?post_type=job&job_location=$matches[1]',
        'top'
    );
    
    // 職種別一覧ページ
    add_rewrite_rule(
        'jobs/position/([^/]+)/?$',
        'index.php?post_type=job&job_position=$matches[1]',
        'top'
    );
	
    // 基本の求人アーカイブページ用のルール
    add_rewrite_rule(
        'jobs/?$',
        'index.php?post_type=job',
        'top'
    );
	
}
add_action('init', 'add_custom_job_rewrite_rules');


function breadcrumb() {
    echo '<div class="breadcrumb">';
    echo '<a href="'.home_url().'">ホーム</a> &gt; ';
    
    if (is_single()) {
        $categories = get_the_category();
        if ($categories) {
            echo '<a href="'.get_category_link($categories[0]->term_id).'">'.$categories[0]->name.'</a> &gt; ';
        }
        echo get_the_title();
    } elseif (is_page()) {
        echo get_the_title();
    } elseif (is_category()) {
        echo single_cat_title('', false);
    }
    
    echo '</div>';
}

/**
 * お気に入り求人機能 - 統合版
 * functions.phpに追加してください
 */

// === JavaScript読み込み機能 ===
function enqueue_favorite_job_scripts() {
    // スクリプトを登録して読み込む
    wp_register_script('favorite-job-script', get_stylesheet_directory_uri() . '/js/favorite-job.js', array('jquery'), '1.0.0', true);
    
    // ローカライズスクリプトを追加（ajaxurl、nonceなどの値をJSに渡す）
    wp_localize_script('favorite-job-script', 'favoriteJobSettings', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'home_url' => home_url('/jobs/'),
        'nonce' => wp_create_nonce('job_favorite_nonce')
    ));
    
    // スクリプトを読み込む
    wp_enqueue_script('favorite-job-script');
}
add_action('wp_enqueue_scripts', 'enqueue_favorite_job_scripts');

// === お気に入り求人の追加・削除処理 ===
function handle_toggle_job_favorite() {
    // ナンス検証（複数のnonceに対応）
    $nonce_keys = array('job_favorite_nonce', 'favorites_nonce');
    $nonce_valid = false;
    
    if (isset($_POST['nonce'])) {
        foreach ($nonce_keys as $key) {
            if (wp_verify_nonce($_POST['nonce'], $key)) {
                $nonce_valid = true;
                break;
            }
        }
    }
    
    if (!$nonce_valid) {
        wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました。'));
        return;
    }
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'ログインが必要です。'));
        return;
    }
    
    $user_id = get_current_user_id();
    $job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
    
    if (!$job_id) {
        wp_send_json_error(array('message' => '無効な求人IDです。'));
        return;
    }
    
    // 現在のお気に入りリストを取得
    $favorites = get_user_meta($user_id, 'user_favorites', true);
    
    if (!is_array($favorites)) {
        $favorites = array();
    }
    
    // お気に入りリストに含まれているかチェック
    $index = array_search($job_id, $favorites);
    
    if ($index !== false) {
        // お気に入りリストに含まれている場合は削除
        unset($favorites[$index]);
        $favorites = array_values($favorites); // インデックスを振り直し
        update_user_meta($user_id, 'user_favorites', $favorites);
        wp_send_json_success(array(
            'status' => 'removed',
            'favorited' => false,
            'message' => 'お気に入りから削除しました。'
        ));
    } else {
        // お気に入りリストに含まれていない場合は追加
        $favorites[] = $job_id;
        update_user_meta($user_id, 'user_favorites', $favorites);
        wp_send_json_success(array(
            'status' => 'added',
            'favorited' => true,
            'message' => 'お気に入りに追加しました。'
        ));
    }
}

// フックの登録（ログイン・非ログイン両方に対応）
add_action('wp_ajax_toggle_job_favorite', 'handle_toggle_job_favorite');
add_action('wp_ajax_nopriv_toggle_job_favorite', 'handle_toggle_job_favorite');

/**
 * ショートコードを追加 - キープ(お気に入り)した求人の数を表示
 * 使用例: [favorite_jobs_count]
 */
function favorite_jobs_count_shortcode() {
    if (!is_user_logged_in()) {
        return '0';
    }
    
    $user_id = get_current_user_id();
    $favorites = get_user_meta($user_id, 'user_favorites', true);
    
    if (!is_array($favorites)) {
        return '0';
    }
    
    return count($favorites);
}
add_shortcode('favorite_jobs_count', 'favorite_jobs_count_shortcode');
/**
 * お気に入り求人機能 - 互換性対応版
 * functions.phpに追加してください
 */

// === JavaScript読み込み機能 ===
if (!function_exists('enqueue_favorite_job_scripts')) {
    function enqueue_favorite_job_scripts() {
        // スクリプトを登録して読み込む
        wp_register_script('favorite-job-script', get_stylesheet_directory_uri() . '/js/favorite-job.js', array('jquery'), '1.0.0', true);
        
        // ローカライズスクリプトを追加
        wp_localize_script('favorite-job-script', 'favoriteJobSettings', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'home_url' => home_url('/jobs/'),
            'nonce' => wp_create_nonce('job_favorite_nonce')
        ));
        
        // スクリプトを読み込む
        wp_enqueue_script('favorite-job-script');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_favorite_job_scripts');

// === お気に入り求人の追加・削除処理 ===
if (!function_exists('handle_toggle_job_favorite')) {
    function handle_toggle_job_favorite() {
        // nonceチェック
        $is_valid_nonce = false;
        
        if (isset($_POST['nonce'])) {
            // job_favorite_nonceのチェック
            if (wp_verify_nonce($_POST['nonce'], 'job_favorite_nonce')) {
                $is_valid_nonce = true;
            }
            
            // favorites_nonceのチェック
            if (!$is_valid_nonce && wp_verify_nonce($_POST['nonce'], 'favorites_nonce')) {
                $is_valid_nonce = true;
            }
        }
        
        if (!$is_valid_nonce) {
            wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました。'));
            return;
        }
        
        // ログインチェック
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'ログインが必要です。'));
            return;
        }
        
        // ユーザーIDと求人IDの取得
        $user_id = get_current_user_id();
        $job_id = 0;
        
        if (isset($_POST['job_id']) && $_POST['job_id']) {
            $job_id = intval($_POST['job_id']);
        }
        
        if (!$job_id) {
            wp_send_json_error(array('message' => '無効な求人IDです。'));
            return;
        }
        
        // 現在のお気に入りリストを取得
        $favorites = get_user_meta($user_id, 'user_favorites', true);
        
        if (empty($favorites) || !is_array($favorites)) {
            $favorites = array();
        }
        
        // お気に入りリストに含まれているかチェック
        $index = array_search($job_id, $favorites);
        
        if ($index !== false) {
            // お気に入りリストに含まれている場合は削除
            unset($favorites[$index]);
            $favorites = array_values($favorites); // インデックスを振り直し
            update_user_meta($user_id, 'user_favorites', $favorites);
            
            $result = array(
                'status' => 'removed',
                'favorited' => false,
                'message' => 'お気に入りから削除しました。'
            );
            
            wp_send_json_success($result);
        } else {
            // お気に入りリストに含まれていない場合は追加
            $favorites[] = $job_id;
            update_user_meta($user_id, 'user_favorites', $favorites);
            
            $result = array(
                'status' => 'added',
                'favorited' => true,
                'message' => 'お気に入りに追加しました。'
            );
            
            wp_send_json_success($result);
        }
    }
}

// フックの登録（ログイン・非ログイン両方に対応）
remove_action('wp_ajax_toggle_job_favorite', 'toggle_job_favorite_handler'); // 既存のハンドラーを削除（もし存在すれば）
add_action('wp_ajax_toggle_job_favorite', 'handle_toggle_job_favorite');
add_action('wp_ajax_nopriv_toggle_job_favorite', 'handle_toggle_job_favorite');

/**
 * ショートコードを追加 - キープ(お気に入り)した求人の数を表示
 * 使用例: [favorite_jobs_count]
 */
if (!function_exists('favorite_jobs_count_shortcode')) {
    function favorite_jobs_count_shortcode() {
        if (!is_user_logged_in()) {
            return '0';
        }
        
        $user_id = get_current_user_id();
        $favorites = get_user_meta($user_id, 'user_favorites', true);
        
        if (empty($favorites) || !is_array($favorites)) {
            return '0';
        }
        
        return (string)count($favorites);
    }
}
add_shortcode('favorite_jobs_count', 'favorite_jobs_count_shortcode');
/**
 * 検索結果ページにおいて、カスタム投稿タイプ「job」のみを表示する
 */
function job_custom_search_filter($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_search) {
        // フロントエンドの検索結果ページでのみ実行
        $query->set('post_type', 'job');
    }
    return $query;
}
add_filter('pre_get_posts', 'job_custom_search_filter');

/**
 * キーワード検索を拡張して、カスタムフィールドも検索対象に含める
 */
function job_custom_search_where($where, $query) {
    global $wpdb;
    
    if (!is_admin() && $query->is_main_query() && $query->is_search) {
        $search_term = get_search_query();
        
        if (!empty($search_term)) {
            // オリジナルの検索条件を保持
            $original_where = $where;
            
            // カスタムフィールドを検索対象に追加
            $custom_fields = array(
                'facility_name',
                'facility_company',
                'facility_address',
                'job_content_title',
                'salary_range',
                'requirements',
                'benefits'
            );
            
            $meta_query = array();
            foreach ($custom_fields as $field) {
                $meta_query[] = $wpdb->prepare("(pm.meta_key = %s AND pm.meta_value LIKE %s)", $field, '%' . $wpdb->esc_like($search_term) . '%');
            }
            
            // メタデータとのJOINを確実にするためにクエリを調整
            // 注意：このアプローチは複雑なため、実際の環境でよく確認してください
            if (!empty($meta_query)) {
                $meta_where = ' OR (' . implode(' OR ', $meta_query) . ')';
                
                // 基本的な検索句の正規表現を使用して置換
                $pattern = '/([\(])\s*' . $wpdb->posts . '\.post_title\s+LIKE\s*(\'[^\']*\')\s*\)/';
                if (preg_match($pattern, $where, $matches)) {
                    $where = str_replace($matches[0], $matches[0] . $meta_where, $where);
                }
            }
        }
    }
    
    return $where;
}
add_filter('posts_where', 'job_custom_search_where', 10, 2);

/**
 * カスタムフィールド検索のためのJOINを追加
 */
function job_custom_search_join($join, $query) {
    global $wpdb;
    
    if (!is_admin() && $query->is_main_query() && $query->is_search) {
        $search_term = get_search_query();
        
        if (!empty($search_term)) {
            $join .= " LEFT JOIN $wpdb->postmeta pm ON ($wpdb->posts.ID = pm.post_id) ";
        }
    }
    
    return $join;
}
add_filter('posts_join', 'job_custom_search_join', 10, 2);

/**
 * 検索結果が重複しないようにする
 */
function job_custom_search_distinct($distinct, $query) {
    if (!is_admin() && $query->is_main_query() && $query->is_search) {
        return "DISTINCT";
    }
    
    return $distinct;
}
add_filter('posts_distinct', 'job_custom_search_distinct', 10, 2);


// スライダーカスタム投稿タイプの登録
function register_slider_post_type() {
    $labels = array(
        'name'                  => 'スライダー',
        'singular_name'         => 'スライド',
        'menu_name'             => 'スライダー',
        'name_admin_bar'        => 'スライド',
        'archives'              => 'スライドアーカイブ',
        'attributes'            => 'スライド属性',
        'all_items'             => 'すべてのスライド',
        'add_new_item'          => '新しいスライドを追加',
        'add_new'               => '新規追加',
        'new_item'              => '新しいスライド',
        'edit_item'             => 'スライドを編集',
        'update_item'           => 'スライドを更新',
        'view_item'             => 'スライドを表示',
        'view_items'            => 'スライドを表示',
        'search_items'          => 'スライドを検索',
    );
    
    $args = array(
        'label'                 => 'スライド',
        'labels'                => $labels,
        'supports'              => array('title'),  // タイトルのみサポート
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 20,
        'menu_icon'             => 'dashicons-images-alt2',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
    );
    
    register_post_type('slide', $args);
}
add_action('init', 'register_slider_post_type');

// スライド用のカスタムフィールドを追加
function slider_custom_meta_boxes() {
    add_meta_box(
        'slider_settings',
        'スライド設定',
        'slider_settings_callback',
        'slide',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'slider_custom_meta_boxes');

// スライド設定のコールバック関数
function slider_settings_callback($post) {
    wp_nonce_field(basename(__FILE__), 'slider_nonce');
    
    // 保存された値を取得
    $slide_image_id = get_post_meta($post->ID, 'slide_image_id', true);
    $slide_image_url = wp_get_attachment_image_url($slide_image_id, 'full');
    $slide_link = get_post_meta($post->ID, 'slide_link', true);
    
    ?>
    <div class="slider-settings-container" style="margin-bottom: 20px;">
        <p>
            <label for="slide_image"><strong>スライド画像：</strong></label><br>
            <input type="hidden" name="slide_image_id" id="slide_image_id" value="<?php echo esc_attr($slide_image_id); ?>" />
            <button type="button" class="button" id="slide_image_button">画像を選択</button>
            <button type="button" class="button" id="slide_image_remove" style="<?php echo empty($slide_image_id) ? 'display:none;' : ''; ?>">画像を削除</button>
            
            <div id="slide_image_preview" style="margin-top: 10px; <?php echo empty($slide_image_url) ? 'display:none;' : ''; ?>">
                <img src="<?php echo esc_url($slide_image_url); ?>" alt="スライド画像" style="max-width: 300px; height: auto;" />
            </div>
        </p>
        
        <p>
            <label for="slide_link"><strong>スライドリンク：</strong></label><br>
            <input type="url" name="slide_link" id="slide_link" value="<?php echo esc_url($slide_link); ?>" style="width: 100%;" />
            <span class="description">スライドをクリックした時に移動するURLを入力してください。空白の場合はリンクしません。</span>
        </p>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // 画像選択ボタンのクリックイベント
        $('#slide_image_button').click(function(e) {
            e.preventDefault();
            
            var image_frame;
            
            // MediaUploader インスタンスが既に存在する場合は再利用
            if (image_frame) {
                image_frame.open();
                return;
            }
            
            // MediaUploader の設定と作成
            image_frame = wp.media({
                title: 'スライド画像を選択',
                button: {
                    text: '画像を使用'
                },
                multiple: false
            });
            
            // 画像が選択されたときの処理
            image_frame.on('select', function() {
                var attachment = image_frame.state().get('selection').first().toJSON();
                $('#slide_image_id').val(attachment.id);
                
                // プレビュー更新
                $('#slide_image_preview img').attr('src', attachment.url);
                $('#slide_image_preview').show();
                $('#slide_image_remove').show();
            });
            
            // MediaUploader を開く
            image_frame.open();
        });
        
        // 画像削除ボタンのクリックイベント
        $('#slide_image_remove').click(function(e) {
            e.preventDefault();
            $('#slide_image_id').val('');
            $('#slide_image_preview').hide();
            $(this).hide();
        });
    });
    </script>
    <?php
}

// スライド設定を保存
function save_slider_meta($post_id) {
    // 自動保存の場合は処理しない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    
    // nonce を確認
    if (!isset($_POST['slider_nonce']) || !wp_verify_nonce($_POST['slider_nonce'], basename(__FILE__))) return;
    
    // 権限を確認
    if (!current_user_can('edit_post', $post_id)) return;
    
    // スライド画像IDを保存
    if (isset($_POST['slide_image_id'])) {
        update_post_meta($post_id, 'slide_image_id', sanitize_text_field($_POST['slide_image_id']));
    }
    
    // スライドリンクを保存
    if (isset($_POST['slide_link'])) {
        update_post_meta($post_id, 'slide_link', esc_url_raw($_POST['slide_link']));
    }
}
add_action('save_post_slide', 'save_slider_meta');

// MediaUploader のスクリプトを読み込む
function slider_admin_scripts() {
    global $post_type;
    if ('slide' === $post_type) {
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'slider_admin_scripts');




// functions.phpに追加
function custom_wpmem_login_redirect($redirect_to, $user) {
    // 特定のページからのログインかどうかをチェック
    if (isset($_POST['is_franchise_login']) && $_POST['is_franchise_login'] === '1') {
        return 'https://testjc-fc.kphd-portal.net/job-list/';
    }
    return $redirect_to;
}
add_filter('wpmem_login_redirect', 'custom_wpmem_login_redirect', 10, 2);

/**
 * メルマガ関連機能の実装
 */

// メルマガ購読者一覧ページを管理メニューに追加
function add_mailmagazine_subscribers_menu() {
    add_menu_page(
        'メルマガ購読者一覧', // ページタイトル
        'メルマガリスト', // メニュータイトル
        'manage_options', // 権限
        'mailmagazine-subscribers', // メニュースラッグ
        'display_mailmagazine_subscribers', // 表示用の関数
        'dashicons-email-alt', // アイコン
        26 // 位置
    );
}
add_action('admin_menu', 'add_mailmagazine_subscribers_menu');

// メルマガ購読者一覧ページの表示
function display_mailmagazine_subscribers() {
    // 管理者権限チェック
    if (!current_user_can('manage_options')) {
        wp_die('アクセス権限がありません。');
    }
    
    // CSVエクスポート処理
    if (isset($_POST['export_csv']) && isset($_POST['mailmagazine_export_nonce']) && 
        wp_verify_nonce($_POST['mailmagazine_export_nonce'], 'mailmagazine_export_action')) {
        
        // 出力バッファリングを無効化（既に開始されている場合は終了）
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // CSVのヘッダー設定
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="mailmagazine_subscribers_' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOMを出力（Excelでの文字化け対策）
        fputs($output, "\xEF\xBB\xBF");
        
        // ヘッダー行 - 指定された順序で
        fputcsv($output, array('登録日', '名前', 'メールアドレス', '職種', 'ご住所(都道府県)'));
        
        // 購読者を取得
        $subscribers = get_mailmagazine_subscribers();
        
        foreach ($subscribers as $user) {
            // 職種情報を取得
            $jobtype = get_user_meta($user->ID, 'jobtype', true);
            $jobtype_display = !empty($jobtype) ? $jobtype : '';
            
            // 都道府県情報を取得
            $prefecture = get_user_meta($user->ID, 'prefectures', true);
            $prefecture_display = !empty($prefecture) ? $prefecture : '';
            
            fputcsv($output, array(
                date('Y/m/d', strtotime($user->user_registered)),
                $user->display_name,
                $user->user_email,
                $jobtype_display,
                $prefecture_display
            ));
        }
        
        fclose($output);
        exit;
    }
    
    // 購読者を取得
    $subscribers = get_mailmagazine_subscribers();
    $total_subscribers = count($subscribers);
    
    // 管理画面の表示
    ?>
    <div class="wrap">
        <h1>メルマガ購読者一覧</h1>
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="post">
                    <?php wp_nonce_field('mailmagazine_export_action', 'mailmagazine_export_nonce'); ?>
                    <input type="submit" name="export_csv" class="button action" value="CSVでエクスポート">
                </form>
            </div>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo $total_subscribers; ?> 件の購読者</span>
            </div>
            <br class="clear">
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-registered">登録日</th>
                    <th scope="col" class="manage-column column-name">名前</th>
                    <th scope="col" class="manage-column column-email">メールアドレス</th>
                    <th scope="col" class="manage-column column-jobtype">職種</th>
                    <th scope="col" class="manage-column column-prefecture">ご住所(都道府県)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($subscribers)) {
                    echo '<tr><td colspan="5">購読者はいません。</td></tr>';
                } else {
                    foreach ($subscribers as $user) {
                        // 職種情報を取得
                        $jobtype = get_user_meta($user->ID, 'jobtype', true);
                        $jobtype_display = !empty($jobtype) ? $jobtype : '未設定';
                        
                        // 都道府県情報を取得
                        $prefecture = get_user_meta($user->ID, 'prefectures', true);
                        $prefecture_display = !empty($prefecture) ? $prefecture : '未設定';
                        ?>
                        <tr>
                            <td><?php echo date('Y/m/d', strtotime($user->user_registered)); ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>">
                                    <?php echo esc_html($user->display_name); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo esc_html($jobtype_display); ?></td>
                            <td><?php echo esc_html($prefecture_display); ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="col" class="manage-column column-registered">登録日</th>
                    <th scope="col" class="manage-column column-name">名前</th>
                    <th scope="col" class="manage-column column-email">メールアドレス</th>
                    <th scope="col" class="manage-column column-jobtype">職種</th>
                    <th scope="col" class="manage-column column-prefecture">ご住所(都道府県)</th>
                </tr>
            </tfoot>
        </table>
        
        <style>
        .column-registered { width: 10%; }
        .column-name { width: 20%; }
        .column-email { width: 30%; }
        .column-jobtype { width: 20%; }
        .column-prefecture { width: 20%; }
        </style>
    </div>
    <?php
}
/**
 * 別の方法でCSVをダウンロードする専用のアクション
 */
function mailmagazine_download_csv_action() {
    // 管理者権限チェック
    if (!current_user_can('manage_options')) {
        wp_die('アクセス権限がありません。');
    }
    
    // nonceチェック
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'download_mailmagazine_csv')) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 出力バッファリングを無効化
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // CSVのヘッダー設定
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="mailmagazine_subscribers_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // BOMを出力（Excelでの文字化け対策）
    fputs($output, "\xEF\xBB\xBF");
    
    // ヘッダー行
    fputcsv($output, array('登録日', '名前', 'メールアドレス'));
    
    // 購読者を取得
    $subscribers = get_mailmagazine_subscribers();
    
    foreach ($subscribers as $user) {
        fputcsv($output, array(
            date('Y/m/d', strtotime($user->user_registered)),
            $user->display_name,
            $user->user_email
        ));
    }
    
    fclose($output);
    exit;
}
add_action('admin_post_download_mailmagazine_csv', 'mailmagazine_download_csv_action');

/**
 * メルマガを購読しているユーザーを取得する関数
 */
function get_mailmagazine_subscribers() {
    // ユーザークエリパラメータ
    $args = array(
        'meta_key'     => 'mailmagazine_preference',
        'meta_value'   => 'subscribe',
        'fields'       => array('ID', 'user_email', 'display_name', 'user_registered')
    );
    
    // クエリ実行
    $subscribers = get_users($args);
    
    return $subscribers;
}

/**
 * 新規ユーザー登録時にメルマガ設定のデフォルト値を設定
 * 権限ごとに異なるデフォルト値を設定
 */
function set_default_mailmagazine_preference($user_id) {
    // ユーザーの権限を取得
    $user = get_userdata($user_id);
    
    // デフォルト値を権限によって設定
    if (in_array('subscriber', (array)$user->roles)) {
        // 購読者(subscriber)の場合は「購読する」をデフォルトに設定
        add_user_meta($user_id, 'mailmagazine_preference', 'subscribe', true);
    } elseif (in_array('agency', (array)$user->roles)) {
        // 加盟教室(agency)の場合は「購読しない」をデフォルトに設定
        add_user_meta($user_id, 'mailmagazine_preference', 'unsubscribe', true);
    } else {
        // その他の権限の場合も「購読しない」をデフォルトに設定
        add_user_meta($user_id, 'mailmagazine_preference', 'unsubscribe', true);
    }
}
add_action('user_register', 'set_default_mailmagazine_preference');
/**
 * ユーザープロフィール画面にメルマガ設定フィールドを追加
 */
function add_mailmagazine_preference_field($user) {
    // 現在の設定を取得
    $mailmagazine_preference = get_user_meta($user->ID, 'mailmagazine_preference', true);
    if (empty($mailmagazine_preference)) {
        $mailmagazine_preference = 'unsubscribe'; // デフォルト値
    }
    ?>
    <h3>メルマガ設定</h3>
    <table class="form-table">
        <tr>
            <th><label for="mailmagazine_preference">メルマガ購読</label></th>
            <td>
                <select name="mailmagazine_preference" id="mailmagazine_preference">
                    <option value="subscribe" <?php selected($mailmagazine_preference, 'subscribe'); ?>>購読する</option>
                    <option value="unsubscribe" <?php selected($mailmagazine_preference, 'unsubscribe'); ?>>購読しない</option>
                </select>
                <p class="description">メールマガジンの購読設定を選択してください。</p>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'add_mailmagazine_preference_field');
add_action('edit_user_profile', 'add_mailmagazine_preference_field');

/**
 * ユーザープロフィール更新時にメルマガ設定を保存
 */
function save_mailmagazine_preference_field($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    
    if (isset($_POST['mailmagazine_preference'])) {
        update_user_meta($user_id, 'mailmagazine_preference', sanitize_text_field($_POST['mailmagazine_preference']));
    }
}
add_action('personal_options_update', 'save_mailmagazine_preference_field');
add_action('edit_user_profile_update', 'save_mailmagazine_preference_field');


/**
 * ユーザーが加盟教室(agency)グループに所属しているかチェックする関数
 */
function is_agency_user() {
    // ユーザーがログインしているか確認
    if (!is_user_logged_in()) {
        return false;
    }
    
    // 現在のユーザー情報を取得
    $user = wp_get_current_user();
    
    // WordPress標準のロールで'agency'を持っているか確認
    return in_array('agency', (array) $user->roles);
}

/**
 * ヘッダーナビゲーションとページアクセスのリダイレクト処理
 */
function agency_user_redirect() {
    // agencyユーザーかどうかをチェック
    if (is_agency_user()) {
        global $wp;
        $current_url = home_url(add_query_arg(array(), $wp->request));
        
        // お気に入りページや会員ページへのアクセスを/job-list/にリダイレクト
        if (strpos($current_url, '/favorites') !== false || 
            strpos($current_url, '/members') !== false) {
            wp_redirect(home_url('/job-list/'));
            exit;
        }
    }
}
add_action('template_redirect', 'agency_user_redirect');

/**
 * ヘッダーリンク修正用のJavaScript
 */
function modify_header_links_for_agency() {
    if (is_agency_user()) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // お気に入りとマイページのリンクを/job-list/に変更
            $('.user-nav a[href*="/favorites"]').attr('href', '<?php echo home_url("/job-list/"); ?>');
            $('.user-nav a[href*="/members"]').attr('href', '<?php echo home_url("/job-list/"); ?>');
        });
        </script>
        <?php
    }
}
add_action('wp_footer', 'modify_header_links_for_agency');

/**
 * 特定のユーザーロールの管理画面アクセスを制限する
 */
function restrict_admin_access() {
    // 現在のユーザー情報を取得
    $user = wp_get_current_user();
    
    // agencyまたはsubscriberロールを持つユーザーの管理画面アクセスを制限
    if (
        !empty($user->ID) && 
        (in_array('agency', (array) $user->roles) || in_array('subscriber', (array) $user->roles))
    ) {
        // 現在のURLが管理画面かどうかを確認
        $screen = get_current_screen();
        
        // プロフィール編集画面は許可（オプション）
        if (is_admin() && (!isset($screen) || $screen->id !== 'profile')) {
            // agencyユーザーはジョブリストページへ、subscriberユーザーはホームページへリダイレクト
            if (in_array('agency', (array) $user->roles)) {
                wp_redirect(home_url('/job-list/'));
            } else {
                wp_redirect(home_url());
            }
            exit;
        }
    }
}
add_action('admin_init', 'restrict_admin_access');

/**
 * 管理バーを非表示にする
 */
function remove_admin_bar_for_specific_roles() {
    if (
        current_user_can('agency') || 
        current_user_can('subscriber')
    ) {
        show_admin_bar(false);
    }
}
add_action('after_setup_theme', 'remove_admin_bar_for_specific_roles');

/**
 * ログイン時のリダイレクト処理
 */
function custom_login_redirect($redirect_to, $request, $user) {
    // ユーザーオブジェクトが有効かチェック
    if (isset($user->roles) && is_array($user->roles)) {
        // agencyユーザーはジョブリストページへリダイレクト
        if (in_array('agency', $user->roles)) {
            return home_url('/job-list/');
        }
        // subscriberユーザーはホームページへリダイレクト
        elseif (in_array('subscriber', $user->roles)) {
            return home_url();
        }
    }
    
    // その他のユーザーは通常のリダイレクト先へ
    return $redirect_to;
}
add_filter('login_redirect', 'custom_login_redirect', 10, 3);

/**
 * AJAX リクエストのアクセス制限を行わない（フロントエンドの機能を維持するため）
 */
function allow_ajax_requests_for_all_users() {
    // 現在のリクエストがAJAXリクエストの場合は制限をバイパス
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    
    // メディアアップロードなどの特定のリクエストも許可
    $allowed_actions = array(
        'upload-attachment',
        'async-upload',
    );
    
    if (isset($_GET['action']) && in_array($_GET['action'], $allowed_actions)) {
        return;
    }
    
    // 通常の管理画面アクセス制限を適用
    restrict_admin_access();
}
add_action('admin_init', 'allow_ajax_requests_for_all_users', 0);  // 優先度0で先に実行


/**
 * 加盟教室ユーザー全員を自動的に確認済みにする関数
 * この関数はサイト読み込み時に一度だけ実行されます
 */
function confirm_all_agency_users() {
    // 既に実行済みか確認
    if (get_option('agency_users_confirmed') === 'yes') {
        return;
    }
    
    // agencyロールのユーザーを全て取得
    $agency_users = get_users(array('role' => 'agency'));
    
    if (!empty($agency_users)) {
        foreach ($agency_users as $user) {
            // 確認済みフラグを設定
            update_user_meta($user->ID, '_wpmem_user_confirmed', time());
            error_log('Agency user confirmed: ' . $user->user_email);
        }
    }
    
    // 実行済みフラグを設定
    update_option('agency_users_confirmed', 'yes');
}
add_action('init', 'confirm_all_agency_users', 1);

/**
 * WP-Membersの確認機能をより確実にバイパスする
 */
function bypass_wpmem_confirmation_check($is_confirmed, $user_id) {
    // ユーザー情報を取得
    $user = get_userdata($user_id);
    
    // agencyロールを持つユーザーの場合は常に確認済みとする
    if ($user && in_array('agency', (array) $user->roles)) {
        return true;
    }
    
    return $is_confirmed;
}
// 最も高い優先度（999）で確認チェックをフック
add_filter('wpmem_is_user_confirmed', 'bypass_wpmem_confirmation_check', 999, 2);

/**
 * ログイン処理前に確認済みステータスを設定
 */
function set_agency_confirmed_before_login() {
    // ログインフォームが送信された場合
    if (isset($_POST['log']) && isset($_POST['pwd'])) {
        // ユーザー名またはメールアドレスを取得
        $username = sanitize_user($_POST['log']);
        
        // ユーザーを特定
        $user = get_user_by('login', $username);
        if (!$user) {
            $user = get_user_by('email', $username);
        }
        
        // ユーザーが存在し、agencyロールを持っている場合
        if ($user && in_array('agency', (array) $user->roles)) {
            // 確認済みフラグを設定
            update_user_meta($user->ID, '_wpmem_user_confirmed', time());
        }
    }
}
add_action('init', 'set_agency_confirmed_before_login', 1);

/**
 * エラーメッセージを完全に抑制
 */
function remove_confirmation_error($error_msg) {
    // 確認関連のエラーメッセージを確認
    if (strpos($error_msg, 'Account not confirmed') !== false || 
        strpos($error_msg, 'confirm') !== false || 
        strpos($error_msg, '確認') !== false) {
        
        // ログインフォームが送信された場合、ユーザーを確認
        if (isset($_POST['log'])) {
            $username = sanitize_user($_POST['log']);
            $user = get_user_by('login', $username);
            if (!$user) {
                $user = get_user_by('email', $username);
            }
            
            if ($user && in_array('agency', (array) $user->roles)) {
                // agencyユーザーの場合はエラーを空にする
                return '';
            }
        }
    }
    
    return $error_msg;
}
add_filter('wpmem_login_failed', 'remove_confirmation_error', 999);
add_filter('wpmem_login_status', 'remove_confirmation_error', 999);

/**
 * 確認メールの送信を防止する
 */
function prevent_confirmation_email($email_args) {
    if (isset($email_args['user_id'])) {
        $user = get_userdata($email_args['user_id']);
        if ($user && in_array('agency', (array) $user->roles)) {
            return false;
        }
    }
    return $email_args;
}
add_filter('wpmem_email_filter', 'prevent_confirmation_email', 999);




/**
 * 検索ワード対応パンくずリスト
 */
function improved_breadcrumb() {
    // 現在のURLを取得
    $current_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $parsed_url = parse_url($current_url);
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query = isset($parsed_url['query']) ? $parsed_url['query'] : '';
    
    // クエリパラメータを解析
    $query_params = array();
    if (!empty($query)) {
        parse_str($query, $query_params);
    }
    
    // 検索キーワードを取得（sパラメータ）
    $search_query = get_search_query();
    if (empty($search_query) && isset($query_params['s'])) {
        $search_query = $query_params['s'];
    }
    
    // パス部分を解析
    $path_parts = explode('/', trim($path, '/'));
    
    // URLパスにjobsを含むか確認
    $is_jobs_path = in_array('jobs', $path_parts);
    $jobs_index = array_search('jobs', $path_parts);
    
    // 検索条件を保存する配列
    $conditions = array();
    
    // パンくずHTMLを構築
    $breadcrumb = '<div class="breadcrumb">';
    $breadcrumb .= '<a href="' . home_url() . '">ホーム</a>';
    $breadcrumb .= ' &gt; <a href="' . home_url('/jobs/') . '">求人情報</a>';
    
    // 求人一覧リンク
    $breadcrumb .= ' &gt; <a href="' . home_url('/jobs/') . '">求人一覧</a>';
    
    // URLパスの解析（例: jobs/location/tokyo/position/nurse）
    if ($is_jobs_path && $jobs_index !== false) {
        $taxonomy_map = array(
            'location' => 'job_location',
            'position' => 'job_position',
            'type' => 'job_type',
            'facility' => 'facility_type',
            'feature' => 'job_feature'
        );
        
        $segments = array();
        
        // URLパスを解析して、タクソノミーとスラッグのペアを抽出
        for ($i = $jobs_index + 1; $i < count($path_parts) - 1; $i += 2) {
            if (isset($path_parts[$i]) && isset($path_parts[$i+1])) {
                $tax_segment = $path_parts[$i];
                $term_slug = $path_parts[$i+1];
                
                if (isset($taxonomy_map[$tax_segment])) {
                    $taxonomy = $taxonomy_map[$tax_segment];
                    $segments[] = array(
                        'segment' => $tax_segment,
                        'slug' => $term_slug,
                        'taxonomy' => $taxonomy
                    );
                }
            }
        }
        
        // パス内の条件でパンくずを構築
        foreach ($segments as $segment) {
            $term = get_term_by('slug', $segment['slug'], $segment['taxonomy']);
            
            if ($term) {
                // 階層を持つタクソノミーで親がある場合（主にlocation）
                if ($segment['segment'] == 'location' && $term->parent != 0) {
                    $parent_terms = array();
                    $parent_id = $term->parent;
                    
                    // 親ターム階層を取得
                    while ($parent_id) {
                        $parent = get_term($parent_id, $segment['taxonomy']);
                        if (is_wp_error($parent)) {
                            break;
                        }
                        $parent_terms[] = array(
                            'term' => $parent,
                            'url' => home_url('/jobs/location/' . $parent->slug . '/')
                        );
                        $parent_id = $parent->parent;
                    }
                    
                    // 親から順に表示
                    foreach (array_reverse($parent_terms) as $parent_data) {
                        $parent = $parent_data['term'];
                        $parent_url = $parent_data['url'];
                        
                        $breadcrumb .= ' &gt; <a href="' . esc_url($parent_url) . '">' . esc_html($parent->name) . '</a>';
                        
                        // 条件にも追加
                        $conditions[] = $parent->name;
                    }
                }
                
                // 現在の条件を追加
                $term_url = home_url('/jobs/' . $segment['segment'] . '/' . $term->slug . '/');
                
                // すべての条件をリンクにする
                $breadcrumb .= ' &gt; <a href="' . esc_url($term_url) . '">' . esc_html($term->name) . '</a>';
                
                // 条件に追加
                $conditions[] = $term->name;
            }
        }
        
        // クエリパラメータの解析（例: ?features[]=mikeiken&features[]=shouyo）
        if (isset($query_params['features']) && is_array($query_params['features'])) {
            // features[]パラメータを解析
            $feature_slugs = $query_params['features'];
            
            foreach ($feature_slugs as $index => $slug) {
                $term = get_term_by('slug', $slug, 'job_feature');
                if ($term && !is_wp_error($term)) {
                    // 特徴用のURLを生成
                    $feature_url = home_url('/jobs/feature/' . $term->slug . '/');
                    
                    // 個別の特徴リンクを追加
                    $breadcrumb .= ' &gt; <a href="' . esc_url($feature_url) . '">' . esc_html($term->name) . '</a>';
                    
                    // 条件に追加
                    $conditions[] = $term->name;
                }
            }
        }
    } 
    // タクソノミーアーカイブページの場合
    elseif (is_tax()) {
        $term = get_queried_object();
        $taxonomy = $term->taxonomy;
        
        // タクソノミー名からURLのセグメント部分を決定
        $tax_segment = '';
        switch ($taxonomy) {
            case 'job_location':
                $tax_segment = 'location';
                break;
            case 'job_position':
                $tax_segment = 'position';
                break;
            case 'job_type':
                $tax_segment = 'type';
                break;
            case 'facility_type':
                $tax_segment = 'facility';
                break;
            case 'job_feature':
                $tax_segment = 'feature';
                break;
        }
        
        // 階層を持つタクソノミーの場合は親も表示
        if ($term->parent != 0) {
            $parents = array();
            $parent_id = $term->parent;
            
            // 親タームを遡って配列に追加
            while ($parent_id) {
                $parent = get_term($parent_id, $taxonomy);
                if (is_wp_error($parent)) {
                    break;
                }
                $parents[] = $parent;
                $parent_id = $parent->parent;
            }
            
            // 親タームを逆順で表示（祖先→子の順）
            foreach (array_reverse($parents) as $parent) {
                // カスタム形式のURLを生成
                $parent_url = home_url('/jobs/' . $tax_segment . '/' . $parent->slug . '/');
                $breadcrumb .= ' &gt; <a href="' . esc_url($parent_url) . '">' . esc_html($parent->name) . '</a>';
                
                // 条件にも追加
                $conditions[] = $parent->name;
            }
        }
        
        // 現在のタームを追加
        $term_url = home_url('/jobs/' . $tax_segment . '/' . $term->slug . '/');
        $breadcrumb .= ' &gt; <a href="' . esc_url($term_url) . '">' . esc_html($term->name) . '</a>';
        
        // 条件にも追加
        $conditions[] = $term->name;
    }
    // 求人アーカイブページの場合
    elseif (is_post_type_archive('job') && empty($search_query)) {
        // 検索キーワードがない場合は単に「求人一覧」を現在地として表示
        // すでに「求人一覧」リンクは追加済み
    }
    // 求人詳細ページの場合
    elseif (is_singular('job')) {
        // エリア情報を階層的に表示
        $job_locations = get_the_terms(get_the_ID(), 'job_location');
        if ($job_locations && !is_wp_error($job_locations)) {
            $location = $job_locations[0];
            
            // 親タームがある場合は階層を表示
            if ($location->parent != 0) {
                $parents = array();
                $parent_id = $location->parent;
                
                while ($parent_id) {
                    $parent = get_term($parent_id, 'job_location');
                    if (is_wp_error($parent)) {
                        break;
                    }
                    $parents[] = $parent;
                    $parent_id = $parent->parent;
                }
                
                foreach (array_reverse($parents) as $parent) {
                    // カスタム形式のURLを生成
                    $parent_url = home_url('/jobs/location/' . $parent->slug . '/');
                    $breadcrumb .= ' &gt; <a href="' . esc_url($parent_url) . '">' . esc_html($parent->name) . '</a>';
                }
            }
            
            // カスタム形式のURLを生成
            $location_url = home_url('/jobs/location/' . $location->slug . '/');
            $breadcrumb .= ' &gt; <a href="' . esc_url($location_url) . '">' . esc_html($location->name) . '</a>';
        }
        
        // 職種情報
        $job_positions = get_the_terms(get_the_ID(), 'job_position');
        if ($job_positions && !is_wp_error($job_positions)) {
            $position = $job_positions[0];
            // カスタム形式のURLを生成
            $position_url = home_url('/jobs/position/' . $position->slug . '/');
            $breadcrumb .= ' &gt; <a href="' . esc_url($position_url) . '">' . esc_html($position->name) . '</a>';
        }
        
        // 求人タイトル
        $facility_name = get_post_meta(get_the_ID(), 'facility_name', true);
        if (!empty($facility_name)) {
            $breadcrumb .= ' &gt; ' . esc_html($facility_name);
        } else {
            $breadcrumb .= ' &gt; ' . get_the_title();
        }
    }
    
    // 検索キーワードがある場合は追加（どのページタイプでも）
    if (!empty($search_query)) {
        $breadcrumb .= ' &gt; <span>' . esc_html($search_query) . '</span><span style="font-size:0.8em;">(検索したワード)</span>';
    }
    
    // パンくずリストを閉じる
    $breadcrumb .= '</div>';
    
    return $breadcrumb;
}

/**
 * パンくずリストを表示する関数
 */
function display_breadcrumb() {
    echo improved_breadcrumb();
}

/**
 * ページタイトルを生成する関数
 */
function get_search_title() {
    // 検索キーワードを取得
    $search_query = get_search_query();
    if (!empty($search_query)) {
        return '「' . esc_html($search_query) . '」の検索結果';
    }
    
    // 条件を収集
    $conditions = array();
    
    // URLからパスパラメータを取得
    $current_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $parsed_url = parse_url($current_url);
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query = isset($parsed_url['query']) ? $parsed_url['query'] : '';
    
    // クエリパラメータを解析
    $query_params = array();
    if (!empty($query)) {
        parse_str($query, $query_params);
    }
    
    // パスからtaxonomyパラメータを取得
    $path_parts = explode('/', trim($path, '/'));
    $jobs_index = array_search('jobs', $path_parts);
    
    if ($jobs_index !== false) {
        $taxonomy_map = array(
            'location' => 'job_location',
            'position' => 'job_position',
            'type' => 'job_type',
            'facility' => 'facility_type',
            'feature' => 'job_feature'
        );
        
        for ($i = $jobs_index + 1; $i < count($path_parts) - 1; $i += 2) {
            if (isset($path_parts[$i]) && isset($path_parts[$i+1])) {
                $tax_segment = $path_parts[$i];
                $term_slug = $path_parts[$i+1];
                
                if (isset($taxonomy_map[$tax_segment])) {
                    $taxonomy = $taxonomy_map[$tax_segment];
                    $term = get_term_by('slug', $term_slug, $taxonomy);
                    
                    if ($term) {
                        $conditions[] = $term->name;
                    }
                }
            }
        }
    }
    
    // クエリパラメータからfeature条件を取得
    if (isset($query_params['features']) && is_array($query_params['features'])) {
        foreach ($query_params['features'] as $slug) {
            $term = get_term_by('slug', $slug, 'job_feature');
            if ($term) {
                $conditions[] = $term->name;
            }
        }
    }
    
    // タクソノミーページの場合
    if (is_tax()) {
        $term = get_queried_object();
        if (!in_array($term->name, $conditions)) {
            $conditions[] = $term->name;
        }
    }
    
    // 条件がある場合は条件タイトルを返す
    if (!empty($conditions)) {
        return implode(' × ', $conditions) . 'の求人情報';
    }
    
    // デフォルト
    return '求人情報一覧';
}

/**
 * 求人詳細ページ用のパンくずリスト関数
 */
function job_detail_breadcrumb() {
    // 基本のパンくずリストを開始
    $breadcrumb = '<div class="breadcrumb">';
    $breadcrumb .= '<a href="' . home_url() . '">ホーム</a> &gt; ';
    
    // 求人詳細ページの場合
    if (is_singular('job')) {
        $post_id = get_the_ID();
        
        // 職種を取得
        $job_positions = get_the_terms($post_id, 'job_position');
        if ($job_positions && !is_wp_error($job_positions)) {
            $position = $job_positions[0];
            $position_url = home_url('/jobs/position/' . $position->slug . '/');
            $breadcrumb .= '<a href="' . esc_url($position_url) . '">' . esc_html($position->name) . '</a> &gt; ';
        }
        
        // エリア情報を階層的に表示（親→子→孫）
        $job_locations = get_the_terms($post_id, 'job_location');
        if ($job_locations && !is_wp_error($job_locations)) {
            // 最も詳細なターム（孫）を見つける
            $max_depth = -1;
            $most_specific_term = null;
            
            foreach ($job_locations as $location) {
                $ancestors = get_ancestors($location->term_id, 'job_location', 'taxonomy');
                $depth = count($ancestors);
                
                if ($depth > $max_depth) {
                    $most_specific_term = $location;
                    $max_depth = $depth;
                }
            }
            
            if ($most_specific_term) {
                // 祖先のタームを取得（親→祖父の順）
                $ancestors = array_reverse(get_ancestors($most_specific_term->term_id, 'job_location', 'taxonomy'));
                
                // 階層順に表示（親→子→孫）
                foreach ($ancestors as $ancestor_id) {
                    $ancestor = get_term($ancestor_id, 'job_location');
                    if (!is_wp_error($ancestor)) {
                        $ancestor_url = home_url('/jobs/location/' . $ancestor->slug . '/');
                        $breadcrumb .= '<a href="' . esc_url($ancestor_url) . '">' . esc_html($ancestor->name) . '</a> &gt; ';
                    }
                }
                
                // 最後に最も詳細なターム（孫）を表示
                $location_url = home_url('/jobs/location/' . $most_specific_term->slug . '/');
                $breadcrumb .= '<a href="' . esc_url($location_url) . '">' . esc_html($most_specific_term->name) . '</a> &gt; ';
            }
        }
        
        // 施設名を表示
        $facility_name = get_post_meta($post_id, 'facility_name', true);
        if (!empty($facility_name)) {
            $breadcrumb .= esc_html($facility_name);
        } else {
            $breadcrumb .= get_the_title();
        }
    } 
    // アーカイブページや検索ページの場合
    else {
        // 求人一覧ページの場合
        if (is_post_type_archive('job')) {
            $breadcrumb .= '求人情報一覧';
        }
        // タクソノミーページの場合
        else if (is_tax()) {
            $term = get_queried_object();
            $taxonomy = $term->taxonomy;
            
            // タクソノミー名からセグメント部分を決定
            $tax_segment = '';
            switch ($taxonomy) {
                case 'job_location':
                    $tax_segment = '地域';
                    break;
                case 'job_position':
                    $tax_segment = '職種';
                    break;
                case 'job_type':
                    $tax_segment = '雇用形態';
                    break;
                case 'facility_type':
                    $tax_segment = '施設タイプ';
                    break;
                case 'job_feature':
                    $tax_segment = '特徴';
                    break;
            }
            
            $breadcrumb .= '<a href="' . home_url('/jobs/') . '">求人情報一覧</a> &gt; ';
            $breadcrumb .= $tax_segment . ' &gt; ';
            
            // 階層を持つタクソノミーの場合は親も表示
            if ($term->parent != 0) {
                $parents = array();
                $parent_id = $term->parent;
                
                while ($parent_id) {
                    $parent = get_term($parent_id, $taxonomy);
                    if (is_wp_error($parent)) {
                        break;
                    }
                    $parents[] = $parent;
                    $parent_id = $parent->parent;
                }
                
                // 親タームを逆順で表示（祖父→親の順）
                foreach (array_reverse($parents) as $parent) {
                    $parent_url = home_url('/jobs/' . $tax_segment . '/' . $parent->slug . '/');
                    $breadcrumb .= '<a href="' . esc_url($parent_url) . '">' . esc_html($parent->name) . '</a> &gt; ';
                }
            }
            
            // 現在のタームを表示
            $breadcrumb .= $term->name;
        }
        // 検索結果ページの場合
        else if (is_search()) {
            $search_query = get_search_query();
            $breadcrumb .= '<a href="' . home_url('/jobs/') . '">求人情報一覧</a> &gt; ';
            $breadcrumb .= '「' . esc_html($search_query) . '」の検索結果';
        }
    }
    
    // パンくずリストを閉じる
    $breadcrumb .= '</div>';
    
    return $breadcrumb;
}

/**
 * パンくずリストを表示する関数
 */
function display_job_breadcrumb() {
    echo job_detail_breadcrumb();
}



/**
 * Contact Form 7でログインユーザー情報を自動表示する機能 (最も確実な方法)
 * functions.phpに追加してください
 */

// フォーム表示前に直接JavaScriptで値を設定
function auto_fill_cf7_with_js() {
    // ユーザーがログインしていない場合は何もしない
    if (!is_user_logged_in()) {
        return;
    }
    
    // 現在のユーザー情報を取得
    $user = wp_get_current_user();
    $user_name = esc_js($user->display_name);
    $user_email = esc_js($user->user_email);
    
    // 画面読み込み時にJavaScriptでフォームに値を設定
    ?>
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        // Contact Form 7の読み込み完了イベントを監視
        if (typeof wpcf7 !== 'undefined') {
            document.addEventListener('wpcf7:renderform', function() {
                console.log('CF7フォームがレンダリングされました');
                fillFormFields();
            });
        }
        
        // フォームに値を設定する関数
        function fillFormFields() {
            // すべてのフォームを取得
            const forms = document.querySelectorAll('.wpcf7-form');
            
            forms.forEach(function(form) {
                // 学校名フィールドを設定
                const schoolNameField = form.querySelector('input[name="school-name"]');
                if (schoolNameField && !schoolNameField.value) {
                    schoolNameField.value = "<?php echo $user_name; ?>";
                    console.log('教室名を設定: <?php echo $user_name; ?>');
                }
                
                // メールフィールドを設定
                const emailField = form.querySelector('input[name="user-email"]');
                if (emailField && !emailField.value) {
                    emailField.value = "<?php echo $user_email; ?>";
                    console.log('メールアドレスを設定: <?php echo $user_email; ?>');
                }
            });
        }
        
        // 最初の実行（ページ読み込み時）
        setTimeout(fillFormFields, 500);
    });
    </script>
    <?php
}
add_action('wp_footer', 'auto_fill_cf7_with_js');

// デバッグのためにユーザー情報をログに出力
function debug_cf7_user_info() {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        error_log('CF7 Debug: ユーザーはログイン中です');
        error_log('CF7 Debug: ユーザー名 = ' . $user->display_name);
        error_log('CF7 Debug: メールアドレス = ' . $user->user_email);
    } else {
        error_log('CF7 Debug: ユーザーは未ログインです');
    }
}
add_action('wp_footer', 'debug_cf7_user_info');



/**
 * 求人カード全体クリックで詳細ページに遷移する機能
 */
function add_job_card_click_functionality() {
    // インラインJavaScriptのみを追加
    ?>
    <script>
    jQuery(document).ready(function($) {
        // ジョブカードのクリックイベントを設定
        $('.job-card').each(function() {
            // カード内の詳細ボタンのURLを取得
            var detailUrl = $(this).find('.detail-view-button').attr('href');
            
            if (detailUrl) {
                // カード自体をクリック可能にする
                $(this).css('cursor', 'pointer');
                
                // カードクリック時の処理
                $(this).on('click', function(e) {
                    // ボタンやリンク、フォーム要素などをクリックした場合はそれらの動作を優先
                    if ($(e.target).is('a, button, input, textarea, select, .keep-button, .keep-button *, .detail-view-button, .detail-view-button *, span.star, .star *')) {
                        return; // カード全体のクリックイベントをキャンセル
                    }
                    
                    // それ以外の部分をクリックした場合は詳細ページへ遷移
                    window.location.href = detailUrl;
                });
            }
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'add_job_card_click_functionality');

/**
 * agency ロールにファイルアップロード機能を追加
 */
function add_upload_capability_to_agency() {
    // agency ロールを取得
    $role = get_role('agency');
    
    // ロールが存在する場合のみ処理
    if ($role) {
        // 基本的なメディア操作権限を追加
        $role->add_cap('upload_files', true);
        $role->add_cap('edit_posts', true);
        $role->add_cap('publish_posts', true);
    }
}
add_action('init', 'add_upload_capability_to_agency', 999);

/**
 * メディアアップローダーのスクリプトを強制読み込み
 */
function force_media_for_agency_pages() {
    // ユーザーがログインしており、カスタム投稿ページにいる場合
    if (is_user_logged_in() && (is_page_template('page-post-job.php') || is_page_template('page-edit-job.php'))) {
        wp_enqueue_media();
    }
}
add_action('wp_enqueue_scripts', 'force_media_for_agency_pages', 20);

/**
 * メディア関連の処理で権限チェックをバイパス
 */
function allow_agency_media_access($allcaps, $caps, $args, $user) {
    // ユーザーが agency ロールを持っている場合
    if (isset($user->roles) && in_array('agency', (array) $user->roles)) {
        // メディア関連の権限を許可
        if (isset($caps[0]) && in_array($caps[0], array('upload_files', 'edit_posts'))) {
            $allcaps['upload_files'] = true;
            $allcaps['edit_posts'] = true;
        }
    }
    return $allcaps;
}
add_filter('user_has_cap', 'allow_agency_media_access', 10, 4);

/**
 * agency ユーザー用のメディア関連権限を強化
 */
function enhance_agency_media_capabilities() {
    // agency ロールを取得
    $role = get_role('agency');
    
    if ($role) {
        // メディア操作に必要な基本権限を追加
        $role->add_cap('upload_files', true);
        $role->add_cap('edit_posts', true);
        $role->add_cap('delete_posts', true);
    }
}
add_action('init', 'enhance_agency_media_capabilities', 1);

/**
 * wp-admin/async-upload.php などのメディア関連ページへのアクセスを許可
 */
function allow_media_access_for_agency() {
    if (!is_admin()) return;

    // 現在のユーザーがagencyロールを持っているか確認
    $user = wp_get_current_user();
    if (in_array('agency', (array) $user->roles)) {
        // 現在のページがメディア関連かチェック
        $page = isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF']) : '';
        $allowed_pages = array(
            'async-upload.php',
            'media-upload.php',
            'upload.php',
            'admin-ajax.php'
        );
        
        // メディア関連のページへのアクセスを許可
        if (in_array($page, $allowed_pages)) {
            return;
        }
        
        // ajaxリクエストの場合も許可
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        
        // 管理画面プロフィール編集も許可
        global $pagenow;
        if ($pagenow == 'profile.php') {
            return;
        }
        
        // それ以外の管理画面ページは通常通りリダイレクト
        wp_redirect(home_url('/job-list/'));
        exit;
    }
}

// 既存の制限関数を削除して新しい関数に置き換え
remove_action('admin_init', 'restrict_admin_access');
add_action('admin_init', 'allow_media_access_for_agency', 1);

/**
 * メディアアップローダーのスクリプトを強制的に読み込む
 */
function enqueue_media_fix_scripts() {
    // 必要なページでのみ読み込む
    if (is_page_template('page-post-job.php') || is_page_template('page-edit-job.php')) {
        // メディアアップローダーのJSを強制読み込み
        wp_enqueue_media();
        
        // カスタムJSを読み込む
        wp_enqueue_script(
            'custom-media-fix',
            get_stylesheet_directory_uri() . '/js/media-fix.js',
            array('jquery', 'media-editor', 'jquery-ui-sortable'),
            '1.0.1',
            true
        );
        
        // AJAXのURLを渡す
        wp_localize_script('custom-media-fix', 'media_vars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'home_url' => home_url(),
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_media_fix_scripts', 999);

/**
 * メディアアップロード権限を加盟教室ユーザーに付与
 */
function add_upload_capability_for_agency() {
    // agency ロールに権限を付与
    $role = get_role('agency');
    if ($role) {
        $role->add_cap('upload_files', true);
    }
}
add_action('init', 'add_upload_capability_for_agency');

/**
 * 各ユーザーが自分のアップロードした画像のみ表示（非管理者向け）
 */
function filter_media_for_current_user($query) {
    // 管理者は全ての画像を表示
    if (current_user_can('administrator')) {
        return $query;
    }
    
    // 現在のユーザーを取得
    $user_id = get_current_user_id();
    
    // メディアクエリの場合はユーザーIDでフィルタリング
    if (isset($query['post_type']) && $query['post_type'] === 'attachment') {
        $query['author'] = $user_id;
    }
    
    return $query;
}
add_filter('ajax_query_attachments_args', 'filter_media_for_current_user');

/**
 * メディア関連ページへのアクセスを許可しつつ、他の管理画面はリダイレクト
 */
function allow_media_for_agency() {
    // 管理画面でない場合や管理者の場合はスキップ
    if (!is_admin() || current_user_can('administrator')) {
        return;
    }
    
    // 現在のユーザーがagencyロールを持つか確認
    $user = wp_get_current_user();
    if (!in_array('agency', (array)$user->roles)) {
        return;
    }

    // AJAXリクエストは許可
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    
    // 現在のページ
    $page = isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF']) : '';
    
    // メディア関連ページリスト
    $allowed_pages = array(
        'async-upload.php',
        'upload.php',
        'media-upload.php',
        'admin-ajax.php',
        'profile.php'
    );
    
    // メディア関連ページ以外はリダイレクト
    if (!in_array($page, $allowed_pages)) {
        wp_redirect(home_url('/job-list/'));
        exit;
    }
}

// 既存の制限関数を削除
remove_action('admin_init', 'restrict_admin_access');
// 新しい制限関数を追加
add_action('admin_init', 'allow_media_for_agency', 1);

/**
 * メディアJS読み込み用の簡易関数
 */
function load_media_js_for_job_pages() {
    if (is_page_template('page-post-job.php') || is_page_template('page-edit-job.php')) {
        wp_enqueue_media();
    }
}
add_action('wp_enqueue_scripts', 'load_media_js_for_job_pages', 20);

/**
 * フロントエンド用の求人ステータス変更・削除処理
 */

// アクションフックの登録
add_action('wp_ajax_frontend_draft_job', 'frontend_set_job_to_draft');
add_action('wp_ajax_frontend_publish_job', 'frontend_set_job_to_publish');
add_action('wp_ajax_frontend_delete_job', 'frontend_delete_job');

/**
 * 求人を下書きに変更（フロントエンド用）
 */
function frontend_set_job_to_draft() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_send_json_error('求人が見つかりません。');
    }
    
    $current_user_id = get_current_user_id();
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_send_json_error('この求人を編集する権限がありません。');
    }
    
    // 下書きに変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'draft'
    ));
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を下書きに変更しました。',
            'redirect' => home_url('/job-list/?status=drafted')
        ));
    } else {
        wp_send_json_error('求人の更新に失敗しました。');
    }
}

/**
 * 求人を公開に変更（フロントエンド用）
 */
function frontend_set_job_to_publish() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_send_json_error('求人が見つかりません。');
    }
    
    $current_user_id = get_current_user_id();
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_send_json_error('この求人を編集する権限がありません。');
    }
    
    // 公開に変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'publish'
    ));
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を公開しました。',
            'redirect' => home_url('/job-list/?status=published')
        ));
    } else {
        wp_send_json_error('求人の更新に失敗しました。');
    }
}

/**
 * 求人を削除（フロントエンド用）
 */
function frontend_delete_job() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        wp_send_json_error('求人が見つかりません。');
    }
    
    $current_user_id = get_current_user_id();
    if ($job_post->post_author != $current_user_id && !current_user_can('administrator')) {
        wp_send_json_error('この求人を削除する権限がありません。');
    }
    
    // 削除
    $result = wp_trash_post($job_id);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を削除しました。',
            'redirect' => home_url('/job-list/?status=deleted')
        ));
    } else {
        wp_send_json_error('求人の削除に失敗しました。');
    }
}

// 単一タクソノミーのページネーション対応
// エリアのページネーション
add_rewrite_rule(
    'jobs/location/([^/]+)/page/([0-9]+)/?$',
    'index.php?post_type=job&job_location=$matches[1]&paged=$matches[2]',
    'top'
);

// 職種のページネーション
add_rewrite_rule(
    'jobs/position/([^/]+)/page/([0-9]+)/?$',
    'index.php?post_type=job&job_position=$matches[1]&paged=$matches[2]',
    'top'
);

// 雇用形態のページネーション
add_rewrite_rule(
    'jobs/type/([^/]+)/page/([0-9]+)/?$',
    'index.php?post_type=job&job_type=$matches[1]&paged=$matches[2]',
    'top'
);

// 施設形態のページネーション
add_rewrite_rule(
    'jobs/facility/([^/]+)/page/([0-9]+)/?$',
    'index.php?post_type=job&facility_type=$matches[1]&paged=$matches[2]',
    'top'
);

// 特徴のページネーション
add_rewrite_rule(
    'jobs/feature/([^/]+)/page/([0-9]+)/?$',
    'index.php?post_type=job&job_feature=$matches[1]&paged=$matches[2]',
    'top'
);
// 基本的な求人一覧ページのページネーション
add_rewrite_rule(
    'jobs/page/([0-9]+)/?$',
    'index.php?post_type=job&paged=$matches[1]',
    'top'
);


/**
 * 求人投稿（job）管理画面の一覧に施設名列を追加
 */

// 管理画面の投稿一覧に施設名の列を追加
function add_job_admin_columns($columns) {
    // タイトル列の後に施設名列を挿入
    $new_columns = array();
    
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        // タイトル列の直後に施設名列を追加
        if ($key === 'title') {
            $new_columns['facility_name'] = '施設名';
        }
    }
    
    return $new_columns;
}
add_filter('manage_job_posts_columns', 'add_job_admin_columns');

// 施設名列の内容を表示
function display_job_admin_column_content($column, $post_id) {
    switch ($column) {
        case 'facility_name':
            $facility_name = get_post_meta($post_id, 'facility_name', true);
            
            if (!empty($facility_name)) {
                echo '<strong>' . esc_html($facility_name) . '</strong>';
                
                // 運営会社名も表示（あれば）
                $facility_company = get_post_meta($post_id, 'facility_company', true);
                if (!empty($facility_company)) {
                    echo '<br><span style="color: #666; font-size: 0.9em;">' . esc_html($facility_company) . '</span>';
                }
            } else {
                echo '<span style="color: #999;">未設定</span>';
            }
            break;
    }
}
add_action('manage_job_posts_custom_column', 'display_job_admin_column_content', 10, 2);

// 施設名列をソート可能にする
function make_job_facility_name_sortable($columns) {
    $columns['facility_name'] = 'facility_name';
    return $columns;
}
add_filter('manage_edit-job_sortable_columns', 'make_job_facility_name_sortable');

// 施設名でのソート処理
function job_facility_name_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    if ('facility_name' === $orderby) {
        $query->set('meta_key', 'facility_name');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'job_facility_name_orderby');

// 管理画面のスタイル調整
function job_admin_column_styles() {
    global $current_screen;
    
    if ($current_screen && $current_screen->post_type === 'job' && $current_screen->base === 'edit') {
        ?>
        <style>
        .wp-list-table .column-facility_name {
            width: 20%;
        }
        .wp-list-table .column-title {
            width: 25%;
        }
        .wp-list-table .column-date {
            width: 10%;
        }
        .wp-list-table .column-author {
            width: 15%;
        }
        </style>
        <?php
    }
}
add_action('admin_head', 'job_admin_column_styles');

// フィルター機能: 施設名で検索できるようにする
function job_admin_search_custom_fields($search, $wp_query) {
    global $wpdb;
    
    if (empty($search) || !is_admin()) {
        return $search;
    }
    
    // 現在のスクリーンが求人投稿の管理画面かチェック
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'job') {
        return $search;
    }
    
    $search_term = $wp_query->query_vars['s'];
    if (!empty($search_term)) {
        $search .= " OR (";
        $search .= "(pm.meta_key = 'facility_name' AND pm.meta_value LIKE '%" . esc_sql($wpdb->esc_like($search_term)) . "%')";
        $search .= " OR (pm.meta_key = 'facility_company' AND pm.meta_value LIKE '%" . esc_sql($wpdb->esc_like($search_term)) . "%')";
        $search .= ")";
    }
    
    return $search;
}
add_filter('posts_search', 'job_admin_search_custom_fields', 10, 2);

// 検索時にメタテーブルをJOINする
function job_admin_search_join($join) {
    global $wpdb;
    
    if (is_admin() && is_search()) {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'job') {
            $join .= " LEFT JOIN $wpdb->postmeta pm ON $wpdb->posts.ID = pm.post_id ";
        }
    }
    
    return $join;
}
add_filter('posts_join', 'job_admin_search_join');

// 重複を避けるためにDISTINCTを追加
function job_admin_search_distinct($distinct) {
    if (is_admin() && is_search()) {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'job') {
            return "DISTINCT";
        }
    }
    
    return $distinct;
}
add_filter('posts_distinct', 'job_admin_search_distinct');

/**
 * 管理画面一覧での表示項目を増やす（オプション）
 */
function enhance_job_admin_columns($columns) {
    // より詳細な情報を表示したい場合は以下をアンコメント
    /*
    $new_columns = array();
    
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'facility_name') {
            $new_columns['job_location'] = 'エリア';
            $new_columns['job_position'] = '職種';
            $new_columns['salary_range'] = '給与';
        }
    }
    
    return $new_columns;
    */
    
    return $columns;
}
// add_filter('manage_job_posts_columns', 'enhance_job_admin_columns', 20);

/**
 * 追加カラムの内容表示（オプション）
 */
function display_enhanced_job_columns($column, $post_id) {
    switch ($column) {
        case 'job_location':
            $locations = get_the_terms($post_id, 'job_location');
            if ($locations && !is_wp_error($locations)) {
                $location_names = array();
                foreach ($locations as $location) {
                    $location_names[] = $location->name;
                }
                echo esc_html(implode(', ', $location_names));
            } else {
                echo '<span style="color: #999;">未設定</span>';
            }
            break;
            
        case 'job_position':
            $positions = get_the_terms($post_id, 'job_position');
            if ($positions && !is_wp_error($positions)) {
                echo esc_html($positions[0]->name);
            } else {
                echo '<span style="color: #999;">未設定</span>';
            }
            break;
            
        case 'salary_range':
            $salary = get_post_meta($post_id, 'salary_range', true);
            if (!empty($salary)) {
                echo esc_html($salary);
            } else {
                echo '<span style="color: #999;">未設定</span>';
            }
            break;
    }
}
// add_action('manage_job_posts_custom_column', 'display_enhanced_job_columns', 10, 2);




/**
 * CSVファイルから既知のパスワードを使用してAgency ロールのユーザーにログイン情報を送信する関数
 */
function send_login_info_to_agency_users_with_known_passwords($csv_file_path) {
    // CSVファイルを読み込み
    if (!file_exists($csv_file_path)) {
        return array(
            'success' => false,
            'message' => 'CSVファイルが見つかりません: ' . $csv_file_path
        );
    }
    
    $csv_data = array();
    if (($handle = fopen($csv_file_path, "r")) !== FALSE) {
        $header = fgetcsv($handle); // ヘッダー行を取得
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row = array_combine($header, $data);
            $csv_data[] = $row;
        }
        fclose($handle);
    }
    
    if (empty($csv_data)) {
        return array(
            'success' => false,
            'message' => 'CSVファイルにデータがありません。'
        );
    }
    
    // agency ロールのユーザーのみをフィルタリング
    $agency_users = array_filter($csv_data, function($user) {
        return isset($user['role']) && stripos($user['role'], 'agency') !== false;
    });
    
    if (empty($agency_users)) {
        return array(
            'success' => false,
            'message' => 'Agency ロールのユーザーが見つかりませんでした。'
        );
    }
    
    $sent_count = 0;
    $failed_users = array();
    
    foreach ($agency_users as $user) {
        // 必要なフィールドが存在するかチェック
        if (empty($user['user_email']) || empty($user['password'])) {
            $failed_users[] = array(
                'email' => $user['user_email'] ?? 'N/A',
                'name' => $user['display_name'] ?? 'N/A',
                'reason' => 'メールアドレスまたはパスワードが不正'
            );
            continue;
        }
        
        // メール送信
        $result = send_agency_login_email_with_password(
            $user['user_email'],
            $user['display_name'] ?? $user['user_login'],
            $user['password']
        );
        
        if ($result) {
            $sent_count++;
            
            // ログに記録
            error_log("ログイン情報送信完了: {$user['user_email']} ({$user['display_name']})");
        } else {
            $failed_users[] = array(
                'email' => $user['user_email'],
                'name' => $user['display_name'] ?? $user['user_login'],
                'reason' => 'メール送信失敗'
            );
            
            error_log("ログイン情報送信失敗: {$user['user_email']} ({$user['display_name']})");
        }
    }
    
    return array(
        'success' => true,
        'total_users' => count($agency_users),
        'sent_count' => $sent_count,
        'failed_count' => count($failed_users),
        'failed_users' => $failed_users,
        'agency_users' => $agency_users
    );
}

/**
 * 既知のパスワードでAgency ユーザーにログイン情報メールを送信
 */
function send_agency_login_email_with_password($email, $display_name, $password) {
    $site_name = get_bloginfo('name');
    $login_url = 'https://testjc-fc.kphd-portal.net/instructor-login/';
    
    // メールの件名
    $subject = "[{$site_name}] ログイン情報のお知らせ";
    
    // メール本文
    $message = "
{$display_name} 様

ようこそ！このサイトにログインするためのデータは次のとおりです。

* ログイン用 URL: {$login_url}
* 登録メールアドレス: {$email}
* パスワード: {$password}

【重要】
- 上記のパスワードでログインしてください。
- このメールは大切に保管してください。
- ログインに関してご不明な点がございましたら、お問い合わせください。

よろしくお願いいたします。

---
{$site_name}
" . get_option('admin_email');
    
    // HTMLメール用のヘッダー
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
    );
    
    // メール送信
    return wp_mail($email, $subject, $message, $headers);
}

/**
 * アップロードされたCSVファイルを処理する関数
 */
function process_uploaded_csv_for_agency_login() {
    // ファイルアップロードの処理
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        return array(
            'success' => false,
            'message' => 'CSVファイルのアップロードに失敗しました。'
        );
    }
    
    $uploaded_file = $_FILES['csv_file'];
    $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
    
    if (strtolower($file_extension) !== 'csv') {
        return array(
            'success' => false,
            'message' => 'CSVファイルのみアップロード可能です。'
        );
    }
    
    // 一時的にファイルを保存
    $upload_dir = wp_upload_dir();
    $temp_file = $upload_dir['path'] . '/temp_agency_users_' . time() . '.csv';
    
    if (!move_uploaded_file($uploaded_file['tmp_name'], $temp_file)) {
        return array(
            'success' => false,
            'message' => 'ファイルの保存に失敗しました。'
        );
    }
    
    // CSVを処理
    $result = send_login_info_to_agency_users_with_known_passwords($temp_file);
    
    // 一時ファイルを削除
    unlink($temp_file);
    
    return $result;
}

/**
 * 管理画面から実行するための関数
 */
function execute_agency_login_sender_with_csv() {
    // 管理者権限チェック
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません。');
    }
    
    // nonce チェック
    if (!wp_verify_nonce($_POST['_wpnonce'], 'send_agency_login_info_csv')) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    $result = process_uploaded_csv_for_agency_login();
    
    if ($result['success']) {
        $message = "ログイン情報の送信が完了しました。\n";
        $message .= "対象ユーザー数: {$result['total_users']}\n";
        $message .= "送信成功: {$result['sent_count']}\n";
        $message .= "送信失敗: {$result['failed_count']}\n";
        
        if (!empty($result['failed_users'])) {
            $message .= "\n送信失敗ユーザー:\n";
            foreach ($result['failed_users'] as $failed_user) {
                $message .= "- {$failed_user['name']} ({$failed_user['email']}) - 理由: {$failed_user['reason']}\n";
            }
        }
        
        wp_admin_notice($message, 'success');
    } else {
        wp_admin_notice($result['message'], 'error');
    }
    
    return $result;
}

/**
 * 管理画面メニューに追加
 */
function add_agency_login_sender_csv_menu() {
    add_management_page(
        'Agency ログイン情報送信 (CSV)',
        'Agency ログイン情報送信 (CSV)',
        'manage_options',
        'agency-login-sender-csv',
        'agency_login_sender_csv_page'
    );
}
add_action('admin_menu', 'add_agency_login_sender_csv_menu');

/**
 * 管理画面ページの表示
 */
function agency_login_sender_csv_page() {
    $result = null;
    
    // POST処理
    if (isset($_POST['send_login_info_csv'])) {
        $result = execute_agency_login_sender_with_csv();
    }
    
    ?>
    <div class="wrap">
        <h1>Agency ユーザーへのログイン情報送信 (CSV使用)</h1>
        
        <div class="notice notice-info">
            <p><strong>CSVファイル形式:</strong></p>
            <p>以下のカラムが必要です: <code>user_login, user_email, display_name, password, role</code></p>
            <p><strong>注意:</strong> role に "agency" が含まれるユーザーのみが対象になります。</p>
        </div>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('send_agency_login_info_csv'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="csv_file">CSVファイル</label></th>
                    <td>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required />
                        <p class="description">
                            ユーザー情報が含まれたCSVファイルを選択してください。<br>
                            形式: user_login, user_email, display_name, password, role
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="send_login_info_csv" class="button-primary" value="CSVを読み込んでログイン情報を送信" />
            </p>
        </form>
        
        <?php if ($result && $result['success'] && !empty($result['agency_users'])): ?>
            <h2>送信されたAgencyユーザー一覧</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>表示名</th>
                        <th>メールアドレス</th>
                        <th>ログイン名</th>
                        <th>役割</th>
                        <th>送信状況</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['agency_users'] as $user): ?>
                        <?php 
                        $failed = array_filter($result['failed_users'], function($failed_user) use ($user) {
                            return $failed_user['email'] === $user['user_email'];
                        });
                        $status = empty($failed) ? '✅ 成功' : '❌ 失敗';
                        ?>
                        <tr>
                            <td><?php echo esc_html($user['display_name']); ?></td>
                            <td><?php echo esc_html($user['user_email']); ?></td>
                            <td><?php echo esc_html($user['user_login']); ?></td>
                            <td><?php echo esc_html($user['role']); ?></td>
                            <td><?php echo $status; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
       
    </div>
    <?php
}

/**
 * WP-CLI コマンドとして実行する場合
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('agency-login-csv', function($args, $assoc_args) {
        if (empty($args[0])) {
            WP_CLI::error('CSVファイルのパスを指定してください。');
            return;
        }
        
        $csv_file = $args[0];
        WP_CLI::line("CSVファイル '{$csv_file}' からAgency ユーザーへのログイン情報送信を開始します...");
        
        $result = send_login_info_to_agency_users_with_known_passwords($csv_file);
        
        if ($result['success']) {
            WP_CLI::success("送信完了: {$result['sent_count']}/{$result['total_users']}");
            
            if (!empty($result['failed_users'])) {
                WP_CLI::warning('送信失敗ユーザー:');
                foreach ($result['failed_users'] as $failed_user) {
                    WP_CLI::line("- {$failed_user['name']} ({$failed_user['email']}) - {$failed_user['reason']}");
                }
            }
        } else {
            WP_CLI::error($result['message']);
        }
    });
}


/**
 * 職種の表示用テキストを取得
 */
function get_job_position_display_text($job_id) {
    $job_positions = wp_get_object_terms($job_id, 'job_position');
    
    if (!empty($job_positions) && !is_wp_error($job_positions)) {
        $position = $job_positions[0]; // ラジオボタンなので最初の1つ
        
        if ($position->slug === 'other') {
            $custom_position = get_post_meta($job_id, 'custom_job_position', true);
            return !empty($custom_position) ? $custom_position : $position->name;
        } else {
            return $position->name;
        }
    }
    
    return '';
}

/**
 * 雇用形態の表示用テキストを取得
 */
function get_job_type_display_text($job_id) {
    $job_types = wp_get_object_terms($job_id, 'job_type');
    
    if (!empty($job_types) && !is_wp_error($job_types)) {
        $type = $job_types[0]; // ラジオボタンなので最初の1つ
        
        if ($type->slug === 'others') {
            $custom_type = get_post_meta($job_id, 'custom_job_type', true);
            return !empty($custom_type) ? $custom_type : $type->name;
        } else {
            return $type->name;
        }
    }
    
    return '';
}

/**
 * 共通情報の更新処理に職員の声を追加
 * 既存の共通情報更新処理に以下を追加してください
 */
function update_common_staff_voices($target_user_id) {
    // 職員の声（配列形式）の処理を追加
    if (isset($_POST['staff_voice_role']) && is_array($_POST['staff_voice_role'])) {
        $voice_items = array();
        $count = count($_POST['staff_voice_role']);
        
        for ($i = 0; $i < $count; $i++) {
            if (!empty($_POST['staff_voice_role'][$i])) { // 職種が入力されている項目のみ保存
                $voice_items[] = array(
                    'image_id' => intval($_POST['staff_voice_image'][$i]),
                    'role' => sanitize_text_field($_POST['staff_voice_role'][$i]),
                    'years' => sanitize_text_field($_POST['staff_voice_years'][$i]),
                    'comment' => wp_kses_post($_POST['staff_voice_comment'][$i])
                );
            }
        }
        
        // 共通職員の声をユーザーメタに保存
        update_user_meta($target_user_id, 'common_staff_voice_items', $voice_items);
        
        // 対象ユーザーの全求人投稿に職員の声を適用
        $user_jobs = get_posts(array(
            'post_type' => 'job',
            'posts_per_page' => -1,
            'author' => $target_user_id,
            'post_status' => array('publish', 'draft', 'pending')
        ));
        
        if (!empty($user_jobs)) {
            foreach ($user_jobs as $job) {
                update_post_meta($job->ID, 'staff_voice_items', $voice_items);
            }
        }
    }
}
// functions.php に追加するコード
function ensure_staff_voice_sync_on_job_save($post_id) {
    // 求人投稿タイプでない場合は何もしない
    if (get_post_type($post_id) !== 'job') {
        return;
    }
    
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // 投稿者IDを取得
    $post_author_id = get_post_field('post_author', $post_id);
    if (empty($post_author_id)) {
        return;
    }
    
    // 共通職員の声を取得
    $common_staff_voice = get_user_meta($post_author_id, 'common_staff_voice_items', true);
    
    // 共通情報がある場合は、この求人投稿にも適用
    if (!empty($common_staff_voice) && is_array($common_staff_voice)) {
        update_post_meta($post_id, 'staff_voice_items', $common_staff_voice);
    }
}
// add_action('save_post_job', 'ensure_staff_voice_sync_on_job_save', 25);

function get_user_redirect_url() {
    return is_user_logged_in() ? '/members/' : '/register/';
}

add_shortcode('user_redirect_url', 'get_user_redirect_url');




/**
 * 管理画面の求人投稿（job）に投稿者選択ドロップダウンを追加
 * functions.phpに追加してください
 */

/**
 * 求人投稿の投稿者選択ドロップダウンを管理画面に追加
 */
function add_job_author_meta_box() {
    add_meta_box(
        'job_author_selection',
        '投稿者選択',
        'render_job_author_meta_box',
        'job',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'add_job_author_meta_box');

/**
 * 投稿者選択メタボックスのレンダリング
 */
function render_job_author_meta_box($post) {
    // nonce フィールドを作成
    wp_nonce_field('save_job_author', 'job_author_nonce');
    
    // 現在の投稿者を取得
    $current_author = $post->post_author;
    
    // agencyロールのユーザーを取得
    $agency_users = get_users(array(
        'role' => 'agency',
        'orderby' => 'display_name',
        'order' => 'ASC'
    ));
    
    // 管理者も選択肢に含める
    $admin_users = get_users(array(
        'role' => 'administrator',
        'orderby' => 'display_name', 
        'order' => 'ASC'
    ));
    
    // 全ユーザーを結合
    $all_users = array_merge($agency_users, $admin_users);
    
    ?>
    <div class="job-author-selection">
        <p>
            <label for="job_post_author"><strong>投稿者を選択:</strong></label>
        </p>
        <p>
            <select name="job_post_author" id="job_post_author" style="width: 100%;">
                <?php if (empty($all_users)): ?>
                    <option value="<?php echo get_current_user_id(); ?>">ユーザーが見つかりません</option>
                <?php else: ?>
                    <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user->ID; ?>" <?php selected($current_author, $user->ID); ?>>
                            <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                            <?php if (in_array('administrator', $user->roles)): ?>
                                - 管理者
                            <?php elseif (in_array('agency', $user->roles)): ?>
                                - 加盟教室
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </p>
        <p class="description">
            選択したユーザーの求人一覧ページに表示されます。<br>
            agencyユーザーの場合、そのユーザーの共通情報も自動適用されます。
        </p>
    </div>
    
    <style>
    .job-author-selection {
        padding: 10px 0;
    }
    
    .job-author-selection label {
        font-weight: 600;
        margin-bottom: 5px;
        display: block;
    }
    
    .job-author-selection select {
        padding: 5px;
        border: 1px solid #ddd;
        border-radius: 3px;
    }
    
    .job-author-selection .description {
        font-size: 12px;
        color: #666;
        margin-top: 8px;
        margin-bottom: 0;
    }
    </style>
    <?php
}

/**
 * 投稿者選択の保存処理
 */
function save_job_author_selection($post_id) {
    // 自動保存の場合は何もしない
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // nonceチェック
    if (!isset($_POST['job_author_nonce']) || !wp_verify_nonce($_POST['job_author_nonce'], 'save_job_author')) {
        return;
    }
    
    // 権限チェック
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // 投稿者IDが送信されている場合
    if (isset($_POST['job_post_author']) && !empty($_POST['job_post_author'])) {
        $new_author_id = intval($_POST['job_post_author']);
        
        // 選択されたユーザーが存在し、適切な権限を持っているか確認
        $new_author = get_userdata($new_author_id);
        if ($new_author && (
            in_array('agency', (array)$new_author->roles) || 
            in_array('administrator', (array)$new_author->roles)
        )) {
            // 投稿者を更新
            wp_update_post(array(
                'ID' => $post_id,
                'post_author' => $new_author_id
            ));
            
            // agencyユーザーの場合は共通情報を適用
            if (in_array('agency', (array)$new_author->roles)) {
                apply_common_info_to_job($post_id, $new_author_id);
            }
        }
    }
}
add_action('save_post_job', 'save_job_author_selection');

/**
 * 共通情報を求人投稿に適用する関数
 */
function apply_common_info_to_job($job_id, $user_id) {
    // ユーザーメタから共通情報を取得
    $common_location_slugs = get_user_meta($user_id, 'common_job_location_slugs', true);
    $common_facility_info = get_user_meta($user_id, 'common_facility_info', true);
    $common_facility_type = get_user_meta($user_id, 'common_facility_type', true);
    $common_full_address = get_user_meta($user_id, 'common_full_address', true);
    $common_staff_voice = get_user_meta($user_id, 'common_staff_voice_items', true);
    
    // 勤務地域の適用
    if (!empty($common_location_slugs) && is_array($common_location_slugs)) {
        wp_set_object_terms($job_id, $common_location_slugs, 'job_location');
    }
    
    // 施設形態の適用
    if (!empty($common_facility_type) && is_array($common_facility_type)) {
        wp_set_object_terms($job_id, $common_facility_type, 'facility_type');
    }
    
    // 事業所情報の適用
    if (!empty($common_facility_info) && is_array($common_facility_info)) {
        foreach ($common_facility_info as $key => $value) {
            update_post_meta($job_id, $key, $value);
        }
    }
    
    // 完全な住所の適用
    if (!empty($common_full_address)) {
        update_post_meta($job_id, 'facility_address', $common_full_address);
    }
    
    // 職員の声の適用
    if (!empty($common_staff_voice) && is_array($common_staff_voice)) {
        update_post_meta($job_id, 'staff_voice_items', $common_staff_voice);
    }
}

/**
 * 管理画面の求人一覧に投稿者情報をより詳しく表示
 */
function enhance_job_admin_author_column($columns) {
    // 既存の投稿者列を置き換え
    if (isset($columns['author'])) {
        $columns['author'] = '投稿者 (ロール)';
    }
    return $columns;
}
add_filter('manage_job_posts_columns', 'enhance_job_admin_author_column');

/**
 * 投稿者列に追加情報を表示
 */
function display_enhanced_job_author_info($column, $post_id) {
    if ($column === 'author') {
        $author_id = get_post_field('post_author', $post_id);
        $author = get_userdata($author_id);
        
        if ($author) {
            echo '<strong>' . esc_html($author->display_name) . '</strong><br>';
            echo '<small>' . esc_html($author->user_email) . '</small><br>';
            
            if (in_array('administrator', (array)$author->roles)) {
                echo '<span style="color: #d63638; font-weight: bold;">管理者</span>';
            } elseif (in_array('agency', (array)$author->roles)) {
                echo '<span style="color: #00a32a; font-weight: bold;">加盟教室</span>';
            } else {
                echo '<span style="color: #787c82;">その他</span>';
            }
        }
    }
}
add_action('manage_job_posts_custom_column', 'display_enhanced_job_author_info', 10, 2);

/**
 * 管理画面で投稿者による絞り込み機能を追加
 */
function add_job_author_filter() {
    global $typenow;
    
    if ($typenow == 'job') {
        // 現在選択されている投稿者
        $selected_author = isset($_GET['author']) ? $_GET['author'] : '';
        
        // agencyユーザーを取得
        $agency_users = get_users(array(
            'role' => 'agency',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        // 管理者も取得
        $admin_users = get_users(array(
            'role' => 'administrator',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));
        
        $all_users = array_merge($agency_users, $admin_users);
        
        if (!empty($all_users)) {
            echo '<select name="author">';
            echo '<option value="">全ての投稿者</option>';
            
            foreach ($all_users as $user) {
                $role_label = '';
                if (in_array('administrator', (array)$user->roles)) {
                    $role_label = ' (管理者)';
                } elseif (in_array('agency', (array)$user->roles)) {
                    $role_label = ' (加盟教室)';
                }
                
                printf(
                    '<option value="%s"%s>%s%s</option>',
                    $user->ID,
                    selected($selected_author, $user->ID, false),
                    esc_html($user->display_name),
                    esc_html($role_label)
                );
            }
            
            echo '</select>';
        }
    }
}
add_action('restrict_manage_posts', 'add_job_author_filter');

/**
 * 新規投稿時のデフォルト投稿者設定（管理画面用）
 */
function set_default_job_author_in_admin() {
    global $post, $typenow;
    
    // 管理画面の新規投稿画面でのみ実行
    if (is_admin() && $typenow == 'job' && (!$post || $post->post_status == 'auto-draft')) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // 最初のagencyユーザーをデフォルトで選択（管理者が作成する場合）
            var $authorSelect = $('#job_post_author');
            if ($authorSelect.length && !$authorSelect.val()) {
                // agencyロールのユーザーがいれば最初のユーザーを選択
                var $agencyOption = $authorSelect.find('option:contains("加盟教室")').first();
                if ($agencyOption.length) {
                    $authorSelect.val($agencyOption.val());
                }
            }
        });
        </script>
        <?php
    }
}
add_action('admin_footer', 'set_default_job_author_in_admin');

/**
 * 管理画面での求人作成時の説明メッセージを追加
 */
function add_job_creation_notice() {
    global $post, $typenow;
    
    if (is_admin() && $typenow == 'job' && (!$post || $post->post_status == 'auto-draft')) {
        ?>
        <div class="notice notice-info">
            <p><strong>求人投稿の注意事項：</strong></p>
            <ul>
                <li>投稿者を選択すると、その人の求人一覧ページに表示されます</li>
                <li>agencyユーザーを選択した場合、そのユーザーの共通情報（施設情報等）が自動的に適用されます</li>
                <li>管理者として投稿する場合は、必要な情報をすべて手動で入力してください</li>
            </ul>
        </div>
        <?php
    }
}
add_action('admin_notices', 'add_job_creation_notice');



/**
 * 求人の編集・削除権限を修正
 * functions.phpに追加してください
 */

/**
 * 既存の求人ステータス変更・削除処理を修正版に置き換え
 */

// 既存の関数を削除してから新しい関数を追加
remove_action('admin_post_draft_job', 'set_job_to_draft');
remove_action('admin_post_publish_job', 'set_job_to_publish');
remove_action('admin_post_delete_job', 'delete_job_post');

remove_action('wp_ajax_frontend_draft_job', 'frontend_set_job_to_draft');
remove_action('wp_ajax_frontend_publish_job', 'frontend_set_job_to_publish');
remove_action('wp_ajax_frontend_delete_job', 'frontend_delete_job');

/**
 * 求人の編集・削除権限をチェックする関数
 */
function can_user_edit_job($job_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // ログインしていない場合は不可
    if (!$user_id) {
        return false;
    }
    
    // 管理者は常に可能
    if (user_can($user_id, 'administrator')) {
        return true;
    }
    
    $job_post = get_post($job_id);
    if (!$job_post || $job_post->post_type !== 'job') {
        return false;
    }
    
    // 投稿者本人は可能
    if ($job_post->post_author == $user_id) {
        return true;
    }
    
    // agencyロールで、かつedit_jobs権限を持っている場合も可能
    $user = get_userdata($user_id);
    if ($user && in_array('agency', (array)$user->roles) && user_can($user_id, 'edit_jobs')) {
        return true;
    }
    
    return false;
}

/**
 * 修正版：求人を下書きに変更（バックエンド用）
 */
function fixed_set_job_to_draft() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'draft_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_die('この求人を編集する権限がありません。');
    }
    
    // 下書きに変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'draft'
    ));
    
    if ($result) {
        wp_redirect(home_url('/job-list/?status=drafted'));
    } else {
        wp_die('求人の更新に失敗しました。');
    }
    exit;
}

/**
 * 修正版：求人を公開に変更（バックエンド用）
 */
function fixed_set_job_to_publish() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'publish_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_die('この求人を編集する権限がありません。');
    }
    
    // 公開に変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'publish'
    ));
    
    if ($result) {
        wp_redirect(home_url('/job-list/?status=published'));
    } else {
        wp_die('求人の更新に失敗しました。');
    }
    exit;
}

/**
 * 修正版：求人を削除（バックエンド用）
 */
function fixed_delete_job_post() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_die('この求人を削除する権限がありません。');
    }
    
    // 削除
    $result = wp_trash_post($job_id);
    
    if ($result) {
        wp_redirect(home_url('/job-list/?status=deleted'));
    } else {
        wp_die('求人の削除に失敗しました。');
    }
    exit;
}

/**
 * 修正版：フロントエンド用求人ステータス変更・削除処理
 */
function fixed_frontend_set_job_to_draft() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_send_json_error('この求人を編集する権限がありません。');
    }
    
    // 下書きに変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'draft'
    ));
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を下書きに変更しました。',
            'redirect' => home_url('/job-list/?status=drafted')
        ));
    } else {
        wp_send_json_error('求人の更新に失敗しました。');
    }
}

function fixed_frontend_set_job_to_publish() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_send_json_error('この求人を編集する権限がありません。');
    }
    
    // 公開に変更
    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_status' => 'publish'
    ));
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を公開しました。',
            'redirect' => home_url('/job-list/?status=published')
        ));
    } else {
        wp_send_json_error('求人の更新に失敗しました。');
    }
}

function fixed_frontend_delete_job() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（修正版）
    if (!can_user_edit_job($job_id)) {
        wp_send_json_error('この求人を削除する権限がありません。');
    }
    
    // 削除
    $result = wp_trash_post($job_id);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => '求人を削除しました。',
            'redirect' => home_url('/job-list/?status=deleted')
        ));
    } else {
        wp_send_json_error('求人の削除に失敗しました。');
    }
}

// 修正版の関数をフックに追加
add_action('admin_post_draft_job', 'fixed_set_job_to_draft');
add_action('admin_post_publish_job', 'fixed_set_job_to_publish');
add_action('admin_post_delete_job', 'fixed_delete_job_post');

add_action('wp_ajax_frontend_draft_job', 'fixed_frontend_set_job_to_draft');
add_action('wp_ajax_frontend_publish_job', 'fixed_frontend_set_job_to_publish');
add_action('wp_ajax_frontend_delete_job', 'fixed_frontend_delete_job');

/**
 * page-edit-job.phpの権限チェックも修正するための関数
 */
function check_job_edit_permission($job_id) {
    if (!is_user_logged_in()) {
        return false;
    }
    
    return can_user_edit_job($job_id);
}

/**
 * agencyロールに必要な追加権限を確実に付与
 */
function ensure_agency_job_permissions() {
    $role = get_role('agency');
    
    if ($role) {
        // 求人関連の権限を追加
        $capabilities = array(
            'edit_jobs' => true,
            'edit_published_jobs' => true,
            'delete_jobs' => true,
            'delete_published_jobs' => true,
            'publish_jobs' => true,
            'read_private_jobs' => false,
            'edit_others_jobs' => false,
            'delete_others_jobs' => false,
            'edit_job' => true,
            'read_job' => true,
            'delete_job' => true,
        );
        
        foreach ($capabilities as $cap => $grant) {
            $role->add_cap($cap, $grant);
        }
    }
}
add_action('init', 'ensure_agency_job_permissions', 11);

/**
 * デバッグ用：ユーザーの権限をログに出力（必要に応じて有効化）
 */
function debug_user_job_permissions($job_id = null, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        error_log('Debug: User not logged in');
        return;
    }
    
    $user = get_userdata($user_id);
    error_log('Debug: User ID: ' . $user_id);
    error_log('Debug: User roles: ' . implode(', ', $user->roles));
    error_log('Debug: User capabilities: ' . print_r($user->allcaps, true));
    
    if ($job_id) {
        $job_post = get_post($job_id);
        error_log('Debug: Job ID: ' . $job_id);
        error_log('Debug: Job author: ' . $job_post->post_author);
        error_log('Debug: Can edit job: ' . (can_user_edit_job($job_id, $user_id) ? 'YES' : 'NO'));
    }
}

// デバッグを有効にする場合は下記のコメントアウトを解除
// add_action('wp_footer', function() {
//     if (is_page_template('page-job-list.php') && is_user_logged_in()) {
//         debug_user_job_permissions();
//     }
// });



/**
 * 求人複製機能
 * functions.phpに追加してください
 */

// === フロントエンド用求人複製処理 ===
add_action('wp_ajax_frontend_duplicate_job', 'frontend_duplicate_job');

/**
 * 求人を複製する（フロントエンド用）
 */
function frontend_duplicate_job() {
    if (!isset($_POST['job_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error('無効なリクエストです。');
    }
    
    $job_id = intval($_POST['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_POST['nonce'], 'frontend_job_action')) {
        wp_send_json_error('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック（編集権限があるユーザーのみ複製可能）
    if (!can_user_edit_job($job_id)) {
        wp_send_json_error('この求人を複製する権限がありません。');
    }
    
    // 求人を複製
    $duplicated_job_id = duplicate_job_post($job_id);
    
    if ($duplicated_job_id && !is_wp_error($duplicated_job_id)) {
        wp_send_json_success(array(
            'message' => '求人を複製しました。',
            'redirect' => home_url('/edit-job/?job_id=' . $duplicated_job_id),
            'new_job_id' => $duplicated_job_id
        ));
    } else {
        $error_message = is_wp_error($duplicated_job_id) ? $duplicated_job_id->get_error_message() : '求人の複製に失敗しました。';
        wp_send_json_error($error_message);
    }
}

/**
 * 求人投稿を複製する関数
 *
 * @param int $job_id 複製元の求人ID
 * @return int|WP_Error 新しい求人のIDまたはエラー
 */
/**
 * 求人投稿を複製する関数（修正版）
 * functions.phpの該当部分を以下に置き換えてください
 */
function duplicate_job_post($job_id) {
    // 元の投稿を取得
    $original_post = get_post($job_id);
    
    if (!$original_post || $original_post->post_type !== 'job') {
        return new WP_Error('invalid_post', '無効な求人IDです。');
    }
    
    // 新しい投稿データを準備
    $new_post_data = array(
        'post_title'   => $original_post->post_title . '（コピー）',
        'post_content' => $original_post->post_content,
        'post_status'  => 'draft', // 複製は下書きとして作成
        'post_type'    => 'job',
        'post_author'  => get_current_user_id(),
        'post_excerpt' => $original_post->post_excerpt,
    );
    
    // 新しい投稿を作成
    $new_job_id = wp_insert_post($new_post_data);
    
    if (is_wp_error($new_job_id)) {
        return $new_job_id;
    }
    
    // カスタムフィールドをコピー
    $meta_fields = array(
        'job_content_title',
        'salary_range',
        'working_hours',
        'holidays',
        'benefits',
        'requirements',
        'application_process',
        'contact_info',
        'bonus_raise',
        'facility_name',
        'facility_company',
        'company_url',
        'facility_address',
        'facility_tel',
        'facility_hours',
        'facility_url',
        'facility_map',
        'facility_zipcode',
        'facility_address_detail',
        'capacity',
        'staff_composition',
        'salary_type',
        'salary_form',
        'salary_min',
        'salary_max',
        'fixed_salary',
        'salary_remarks',
        'custom_job_position',
        'custom_job_type',
        'daily_schedule_items',
        'staff_voice_items',
        'job_thumbnail_ids'
    );
    
    foreach ($meta_fields as $meta_key) {
        $meta_value = get_post_meta($job_id, $meta_key, true);
        if (!empty($meta_value)) {
            update_post_meta($new_job_id, $meta_key, $meta_value);
        }
    }
    
    // タクソノミーをコピー（修正版）
    $taxonomies = array('job_location', 'job_position', 'job_type', 'facility_type', 'job_feature');
    
    foreach ($taxonomies as $taxonomy) {
        // タームをスラッグで取得してコピー
        $terms = wp_get_object_terms($job_id, $taxonomy, array('fields' => 'slugs'));
        
        if (!empty($terms) && !is_wp_error($terms)) {
            // デバッグログ出力（必要に応じて）
            error_log("複製処理: {$taxonomy} のターム数: " . count($terms) . " - タームリスト: " . implode(', ', $terms));
            
            // タクソノミーを設定
            $result = wp_set_object_terms($new_job_id, $terms, $taxonomy);
            
            if (is_wp_error($result)) {
                error_log("複製処理エラー: {$taxonomy} の設定に失敗 - " . $result->get_error_message());
            } else {
                error_log("複製処理成功: {$taxonomy} が正常に設定されました");
            }
        } else {
            // 元の投稿にタームが設定されていない場合の処理
            wp_set_object_terms($new_job_id, array(), $taxonomy);
            error_log("複製処理: {$taxonomy} にはタームが設定されていません");
        }
    }
    
    // サムネイル画像をコピー
    $thumbnail_id = get_post_thumbnail_id($job_id);
    if ($thumbnail_id) {
        set_post_thumbnail($new_job_id, $thumbnail_id);
    }
    
    // 複数サムネイル画像をコピー
    $thumbnail_ids = get_post_meta($job_id, 'job_thumbnail_ids', true);
    if (!empty($thumbnail_ids) && is_array($thumbnail_ids)) {
        update_post_meta($new_job_id, 'job_thumbnail_ids', $thumbnail_ids);
    }
    
    // 複製完了後の追加処理（ユーザーの共通情報を適用）
    $current_user_id = get_current_user_id();
    
    // ユーザーメタから共通情報を取得して適用
    $common_location_slugs = get_user_meta($current_user_id, 'common_job_location_slugs', true);
    $common_facility_info = get_user_meta($current_user_id, 'common_facility_info', true);
    $common_facility_type = get_user_meta($current_user_id, 'common_facility_type', true);
    $common_full_address = get_user_meta($current_user_id, 'common_full_address', true);
    $common_staff_voice = get_user_meta($current_user_id, 'common_staff_voice_items', true);
    
    // 勤務地域の適用（共通情報が優先）
    if (!empty($common_location_slugs) && is_array($common_location_slugs)) {
        wp_set_object_terms($new_job_id, $common_location_slugs, 'job_location');
        error_log("複製処理: 共通勤務地域を適用しました");
    }
    
    // 施設形態の適用（共通情報が優先）
    if (!empty($common_facility_type) && is_array($common_facility_type)) {
        $facility_result = wp_set_object_terms($new_job_id, $common_facility_type, 'facility_type');
        if (is_wp_error($facility_result)) {
            error_log("複製処理エラー: 共通施設形態の適用に失敗 - " . $facility_result->get_error_message());
        } else {
            error_log("複製処理成功: 共通施設形態を適用しました - " . implode(', ', $common_facility_type));
        }
    }
    
    // 事業所情報の適用（共通情報が優先）
    if (!empty($common_facility_info) && is_array($common_facility_info)) {
        foreach ($common_facility_info as $key => $value) {
            if (!empty($value)) {
                update_post_meta($new_job_id, $key, $value);
            }
        }
        error_log("複製処理: 共通事業所情報を適用しました");
    }
    
    // 完全な住所の適用（共通情報が優先）
    if (!empty($common_full_address)) {
        update_post_meta($new_job_id, 'facility_address', $common_full_address);
        error_log("複製処理: 共通住所情報を適用しました");
    }
    
    // 職員の声の適用（共通情報が優先）
    if (!empty($common_staff_voice) && is_array($common_staff_voice)) {
        update_post_meta($new_job_id, 'staff_voice_items', $common_staff_voice);
        error_log("複製処理: 共通職員の声を適用しました");
    }
    
    return $new_job_id;
}

/**
 * デバッグ用：複製後の施設形態確認関数
 * 複製がうまくいかない場合のデバッグに使用
 */
function debug_duplicated_job_facility_type($job_id) {
    $facility_types = wp_get_object_terms($job_id, 'facility_type');
    
    error_log("デバッグ: 求人ID {$job_id} の施設形態:");
    if (empty($facility_types) || is_wp_error($facility_types)) {
        error_log("  - 施設形態が設定されていません");
    } else {
        foreach ($facility_types as $term) {
            error_log("  - ターム: {$term->name} (スラッグ: {$term->slug}, ID: {$term->term_id})");
        }
    }
    
    // ユーザーの共通設定も確認
    $current_user_id = get_current_user_id();
    $common_facility_type = get_user_meta($current_user_id, 'common_facility_type', true);
    
    error_log("ユーザーID {$current_user_id} の共通施設形態:");
    if (empty($common_facility_type)) {
        error_log("  - 共通施設形態が設定されていません");
    } else {
        error_log("  - 共通設定: " . print_r($common_facility_type, true));
    }
}

// === バックエンド用求人複製処理（管理画面用） ===
add_action('admin_post_duplicate_job', 'backend_duplicate_job');

/**
 * 求人を複製する（バックエンド用）
 */
function backend_duplicate_job() {
    if (!isset($_GET['job_id']) || !isset($_GET['_wpnonce'])) {
        wp_die('無効なリクエストです。');
    }
    
    $job_id = intval($_GET['job_id']);
    
    // ナンス検証
    if (!wp_verify_nonce($_GET['_wpnonce'], 'duplicate_job_' . $job_id)) {
        wp_die('セキュリティチェックに失敗しました。');
    }
    
    // 権限チェック
    if (!can_user_edit_job($job_id)) {
        wp_die('この求人を複製する権限がありません。');
    }
    
    // 求人を複製
    $new_job_id = duplicate_job_post($job_id);
    
    if ($new_job_id && !is_wp_error($new_job_id)) {
        // 複製成功：編集ページへリダイレクト
        wp_redirect(home_url('/edit-job/?job_id=' . $new_job_id . '&duplicated=true'));
    } else {
        // 複製失敗：エラーメッセージとともに元のページへ戻る
        wp_redirect(home_url('/job-list/?error=duplicate_failed'));
    }
    exit;
}

/**
 * 求人一覧ページで複製ボタンのnonceを生成するヘルパー関数
 */
function get_duplicate_job_nonce($job_id) {
    return wp_create_nonce('duplicate_job_' . $job_id);
}

/**
 * 複製ボタンのURLを生成するヘルパー関数
 */
function get_duplicate_job_url($job_id) {
    return admin_url('admin-post.php?action=duplicate_job&job_id=' . $job_id . '&_wpnonce=' . get_duplicate_job_nonce($job_id));
}
