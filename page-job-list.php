<?php
/**
 * Template Name: 加盟教室用の募集求人一覧ページ
 * ログインユーザーが投稿した求人を一覧表示するテンプレート
 */

// 専用のヘッダーを読み込み
include(get_stylesheet_directory() . '/agency-header.php');

// メディアアップローダーのスクリプトを読み込む
wp_enqueue_media();
wp_enqueue_script('jquery-ui-sortable');

// ログインチェック
if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// 現在のユーザー情報を取得
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// ユーザーが加盟教室（agency）の権限を持っているかチェック
$is_agency = in_array('agency', $current_user->roles);
if (!$is_agency && !current_user_can('administrator')) {
    echo '<div class="error-message">この機能を利用する権限がありません。</div>';
    include(get_stylesheet_directory() . '/agency-footer.php');
    exit;
}

// === 管理者向けの特別な処理 ===
$target_user_id = $current_user_id; // デフォルトは現在のユーザー
$show_common_info_form = true; // 共通情報フォームを表示するかどうか

if (current_user_can('administrator')) {
    // 管理者の場合、特定のユーザーを指定するUIを提供
    $target_user_id = isset($_GET['target_user']) ? intval($_GET['target_user']) : null;
    
    // 対象ユーザーが指定されていない場合は、共通情報フォームを非表示
    if (!$target_user_id) {
        $show_common_info_form = false;
    } else {
        // 指定されたユーザーが存在し、agencyロールを持つかチェック
        $target_user = get_userdata($target_user_id);
        if (!$target_user || !in_array('agency', (array)$target_user->roles)) {
            $show_common_info_form = false;
            $target_user_id = null;
        }
    }
}

// 共通情報の更新処理（対象ユーザーが明確な場合のみ）
if (isset($_POST['update_common_info']) && isset($_POST['common_nonce']) && 
    wp_verify_nonce($_POST['common_nonce'], 'update_common_info') && $target_user_id) {
    
    // 勤務地域の準備
    $job_location_slugs_to_save = array();
    if (!empty($_POST['region_value'])) $job_location_slugs_to_save[] = sanitize_text_field($_POST['region_value']);
    if (!empty($_POST['prefecture_value'])) $job_location_slugs_to_save[] = sanitize_text_field($_POST['prefecture_value']);
    if (!empty($_POST['city_value'])) $job_location_slugs_to_save[] = sanitize_text_field($_POST['city_value']);
    
    // 事業所情報の準備
    $facility_info_to_save = array(
        'facility_name' => sanitize_text_field($_POST['facility_name']),
        'facility_company' => sanitize_text_field($_POST['facility_company']),
        'company_url' => esc_url_raw($_POST['company_url']),
        'facility_zipcode' => sanitize_text_field($_POST['facility_zipcode']),
        'facility_address_detail' => sanitize_text_field($_POST['facility_address_detail']),
        'facility_map' => wp_kses(stripslashes_deep($_POST['facility_map']), array( 
            'iframe' => array(
                'src'             => true,
                'width'           => true,
                'height'          => true,
                'frameborder'     => true,
                'style'           => true,
                'allowfullscreen' => true,
                'loading'         => true, 
                'referrerpolicy'  => true, 
            )
        )),
        'capacity' => sanitize_text_field($_POST['capacity']),
        'staff_composition' => wp_kses_post($_POST['staff_composition']),
        'facility_tel' => sanitize_text_field($_POST['facility_tel']),
        'facility_hours' => sanitize_text_field($_POST['facility_hours']),
        'facility_url' => esc_url_raw($_POST['facility_url'])
    );
    
    // 施設形態の準備
    $facility_type_to_save = isset($_POST['facility_type']) && is_array($_POST['facility_type']) ? array_map('sanitize_text_field', $_POST['facility_type']) : array();

    // 完全な住所の計算
    $prefecture_name_for_address = '';
    $city_name_for_address = '';
    if (!empty($_POST['prefecture_value'])) {
        $term_pref = get_term_by('slug', sanitize_text_field($_POST['prefecture_value']), 'job_location');
        if ($term_pref && !is_wp_error($term_pref)) $prefecture_name_for_address = $term_pref->name;
    }
    if (!empty($_POST['city_value'])) {
        $term_city = get_term_by('slug', sanitize_text_field($_POST['city_value']), 'job_location');
        if ($term_city && !is_wp_error($term_city)) $city_name_for_address = $term_city->name;
    }
    
    $full_address_to_save = '';
    if (!empty($facility_info_to_save['facility_zipcode'])) {
        $full_address_to_save = '〒' . $facility_info_to_save['facility_zipcode'];
    }
    $address_parts_for_full = array_filter([$prefecture_name_for_address, $city_name_for_address, $facility_info_to_save['facility_address_detail']]);
    if (!empty($address_parts_for_full)){
        $full_address_to_save .= (empty($full_address_to_save) ? '' : ' ') . implode(' ', $address_parts_for_full);
    }
    $full_address_to_save = trim($full_address_to_save);

    // 対象ユーザーのメタとして共通情報を保存
    update_user_meta($target_user_id, 'common_job_location_slugs', $job_location_slugs_to_save);
    update_user_meta($target_user_id, 'common_facility_info', $facility_info_to_save);
    update_user_meta($target_user_id, 'common_facility_type', $facility_type_to_save);
    update_user_meta($target_user_id, 'common_full_address', $full_address_to_save);
    
    // 対象ユーザーの全求人投稿を取得
    $user_jobs_args_for_update = array(
        'post_type' => 'job',
        'posts_per_page' => -1,
        'author' => $target_user_id,
        'post_status' => array('publish', 'draft', 'pending')
    );
    
    $user_jobs_to_update = get_posts($user_jobs_args_for_update);
    
    // 各求人投稿に共通情報を適用
    if (!empty($user_jobs_to_update)) {
        foreach ($user_jobs_to_update as $job_to_update_item) {
            // 勤務地域の更新
            if (!empty($job_location_slugs_to_save)) {
                wp_set_object_terms($job_to_update_item->ID, $job_location_slugs_to_save, 'job_location');
            } else { 
                wp_set_object_terms($job_to_update_item->ID, array(), 'job_location');
            }
            
            // 施設形態の更新
            if (!empty($facility_type_to_save)) {
                wp_set_object_terms($job_to_update_item->ID, $facility_type_to_save, 'facility_type');
            } else { 
                wp_set_object_terms($job_to_update_item->ID, array(), 'facility_type');
            }
            
            // 事業所情報の更新
            foreach ($facility_info_to_save as $key_facility => $value_facility) {
                update_post_meta($job_to_update_item->ID, $key_facility, $value_facility);
            }
            // 完全な住所も更新
            update_post_meta($job_to_update_item->ID, 'facility_address', $full_address_to_save);
        }
    }
    // 職員の声（配列形式）の処理
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
    
    // 対象ユーザーのメタとして職員の声を保存
    update_user_meta($target_user_id, 'common_staff_voice_items', $voice_items);
    
    // 対象ユーザーの全求人投稿に職員の声を適用
    if (!empty($user_jobs_to_update)) {
        foreach ($user_jobs_to_update as $job_to_update_item) {
            update_post_meta($job_to_update_item->ID, 'staff_voice_items', $voice_items);
        }
    }
}
    $common_update_success = true;
}

// 共通情報を対象ユーザーのメタから取得
$saved_common_location_slugs = $target_user_id ? get_user_meta($target_user_id, 'common_job_location_slugs', true) : array();
$saved_common_facility_info = $target_user_id ? get_user_meta($target_user_id, 'common_facility_info', true) : array();
$saved_common_facility_type = $target_user_id ? get_user_meta($target_user_id, 'common_facility_type', true) : array();

// フォーム表示用の値がない場合は、配列や空文字で初期化
if (!is_array($saved_common_location_slugs)) $saved_common_location_slugs = array();
if (!is_array($saved_common_facility_info)) $saved_common_facility_info = array();
if (!is_array($saved_common_facility_type)) $saved_common_facility_type = array();

// 対象ユーザーの求人からサンプルを取得
$sample_job_id_for_fallback_display = null; 
if ($target_user_id && empty($saved_common_facility_info['facility_name'])) { 
    $user_jobs_for_sample_fb_display = get_posts(array(
        'post_type' => 'job', 
        'posts_per_page' => 1,
        'author' => $target_user_id,
        'post_status' => array('publish', 'draft', 'pending')
    ));
    if (!empty($user_jobs_for_sample_fb_display)) {
        $sample_job_id_for_fallback_display = $user_jobs_for_sample_fb_display[0]->ID;
    }
}

// 表示用ヘルパー関数
function get_common_info_value_for_display($key_display, $default_display = '') {
    global $saved_common_facility_info, $sample_job_id_for_fallback_display;
    if (isset($saved_common_facility_info[$key_display]) && $saved_common_facility_info[$key_display] !== '') {
        return $saved_common_facility_info[$key_display];
    } elseif ($sample_job_id_for_fallback_display) {
        $meta_value_display = get_post_meta($sample_job_id_for_fallback_display, $key_display, true);
        if ($meta_value_display !== '') return $meta_value_display;
    }
    return $default_display;
}

// JavaScript で使用する初期値のための PHP 変数
$js_initial_region_slug_val = '';
$js_initial_prefecture_slug_val = '';
$js_initial_city_slug_val = '';

if (!empty($saved_common_location_slugs)) {
    if (isset($saved_common_location_slugs[0])) $js_initial_region_slug_val = $saved_common_location_slugs[0];
    if (isset($saved_common_location_slugs[1])) $js_initial_prefecture_slug_val = $saved_common_location_slugs[1];
    if (isset($saved_common_location_slugs[2])) $js_initial_city_slug_val = $saved_common_location_slugs[2];
} elseif ($sample_job_id_for_fallback_display) { 
    $terms_fb_display = wp_get_object_terms($sample_job_id_for_fallback_display, 'job_location', array('fields' => 'all'));
    if(!is_wp_error($terms_fb_display) && !empty($terms_fb_display)){
        foreach($terms_fb_display as $term_fb_display) {
            $ancestors_fb_display = get_ancestors($term_fb_display->term_id, 'job_location', 'taxonomy');
            if (count($ancestors_fb_display) == 0) $js_initial_region_slug_val = $term_fb_display->slug;
            else if (count($ancestors_fb_display) == 1) $js_initial_prefecture_slug_val = $term_fb_display->slug;
            else if (count($ancestors_fb_display) == 2) $js_initial_city_slug_val = $term_fb_display->slug;
        }
    }
}
?>

<div class="job-list-container">
    <h1 class="page-title">求人情報管理</h1>
    
    <?php if (current_user_can('administrator')): ?>
    <!-- 管理者用：対象ユーザー選択UI -->
    <div class="admin-user-selector">
        <h2>管理者用：対象ユーザー選択</h2>
        <form method="get" class="user-selector-form">
            <label for="target_user">編集対象の加盟教室ユーザーを選択：</label>
            <select name="target_user" id="target_user">
                <option value="">ユーザーを選択してください</option>
                <?php
                // agencyロールのユーザー一覧を取得
                $agency_users = get_users(array('role' => 'agency'));
                foreach ($agency_users as $agency_user) {
                    $selected = ($target_user_id == $agency_user->ID) ? 'selected' : '';
                    echo '<option value="' . $agency_user->ID . '" ' . $selected . '>' . 
                         esc_html($agency_user->display_name) . ' (' . esc_html($agency_user->user_email) . ')</option>';
                }
                ?>
            </select>
            <input type="submit" value="選択" class="btn-select-user">
        </form>
        
        <?php if ($target_user_id): ?>
        <div class="selected-user-info">
            <p><strong>現在編集中のユーザー：</strong> <?php echo esc_html(get_userdata($target_user_id)->display_name); ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="job-action-buttons">
        <a href="<?php echo esc_url(home_url('/post-job/')); ?>" class="btn-new-job">新しい求人を投稿</a>
    </div>

    <?php if (isset($common_update_success) && $common_update_success): ?>
    <div class="status-message success">共通情報を更新しました。</div>
    <?php endif; ?>

    <?php if ($show_common_info_form): ?>
    <div class="common-info-section">
        <div class="accordion-header" id="common-info-header">
            <h2 class="section-title">
                <span class="accordion-title">共通情報の編集</span>
                <span class="accordion-icon">▼</span>
            </h2>
            <p class="section-description">
                ここで設定した情報は、<?php echo current_user_can('administrator') && $target_user_id ? esc_html(get_userdata($target_user_id)->display_name) . 'さん' : 'あなた'; ?>の全ての求人投稿に適用されます。
            </p>
        </div>
        
        <div class="accordion-content" id="common-info-content" style="display: none;">
            <form method="post" class="common-info-form">
                <?php wp_nonce_field('update_common_info', 'common_nonce'); ?>
                
                <div class="form-section">
                    <h3 class="form-section-title">勤務地域</h3>
                    <div class="form-row">
                        <label>勤務地域 <span class="required">*</span></label>
                        <div class="location-selector">
                            <div class="location-level">
                                <select id="region-select" class="location-dropdown">
                                    <option value="">地域を選択</option>
                                    <?php 
                                    $parent_terms_for_form = get_terms(array('taxonomy' => 'job_location','hide_empty' => false,'parent' => 0));
                                    if ($parent_terms_for_form && !is_wp_error($parent_terms_for_form)) {
                                        foreach ($parent_terms_for_form as $term_for_form) {
                                            echo '<option value="' . esc_attr($term_for_form->term_id) . '" data-slug="' . esc_attr($term_for_form->slug) . '">' . esc_html($term_for_form->name) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="location-level">
                                <select id="prefecture-select" class="location-dropdown" disabled><option value="">都道府県を選択</option></select>
                            </div>
                            <div class="location-level">
                                <select id="city-select" class="location-dropdown" disabled><option value="">市区町村を選択</option></select>
                            </div>
                        </div>
                        <div class="selected-location-display">
                            <span>選択中: </span>
                            <span id="selected-region-text"></span>
                            <span id="selected-prefecture-text"></span>
                            <span id="selected-city-text"></span>
                        </div>
                        <input type="hidden" id="region-value" name="region_value" value="<?php echo esc_attr($js_initial_region_slug_val); ?>">
                        <input type="hidden" id="prefecture-value" name="prefecture_value" value="<?php echo esc_attr($js_initial_prefecture_slug_val); ?>">
                        <input type="hidden" id="city-value" name="city_value" value="<?php echo esc_attr($js_initial_city_slug_val); ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3 class="form-section-title">事業所の情報</h3>
                    <div class="form-row">
                        <label for="facility_name">施設名 <span class="required">*</span></label>
                        <input type="text" id="facility_name" name="facility_name" value="<?php echo esc_attr(get_common_info_value_for_display('facility_name')); ?>" required>
                    </div>
                    <div class="form-row">
                        <label for="facility_company">運営会社名 <span class="required">*</span></label>
                        <input type="text" id="facility_company" name="facility_company" value="<?php echo esc_attr(get_common_info_value_for_display('facility_company')); ?>" required>
                    </div>
                    <div class="form-row">
                        <label for="company_url">運営会社のWebサイトURL</label>
                        <input type="url" id="company_url" name="company_url" value="<?php echo esc_url(get_common_info_value_for_display('company_url')); ?>" placeholder="https://example.com">
                    </div>
                    <div class="form-row">
                        <label>施設住所 <span class="required">*</span></label>
                        <div class="address-container">
                            <div class="address-row">
                                <label for="facility_zipcode">郵便番号 <span class="required">*</span></label>
                                <input type="text" id="facility_zipcode" name="facility_zipcode" value="<?php echo esc_attr(get_common_info_value_for_display('facility_zipcode')); ?>" placeholder="123-4567" required>
                            </div>
                            <div class="address-row">
                                <label>都道府県・市区町村</label>
                                <div id="location_display" class="location-display">
                                    <span class="location-empty">上記で選択した地域が反映されます</span>
                                </div>
                                <p class="form-hint">※ 上記「勤務地域」で選択した都道府県・市区町村が反映されます</p>
                            </div>
                            <div class="address-row">
                                <label for="facility_address_detail">町名番地・ビル名 <span class="required">*</span></label>
                                <input type="text" id="facility_address_detail" name="facility_address_detail" value="<?php echo esc_attr(get_common_info_value_for_display('facility_address_detail')); ?>" placeholder="○○町1-2-3 △△ビル5階" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="facility_map">GoogleMap埋め込みコード <span class="required">*</span></label>
                        <textarea id="facility_map" name="facility_map" rows="5" required placeholder="GoogleMapの埋め込みコード（iframeタグ）を貼り付けてください"><?php echo esc_textarea(get_common_info_value_for_display('facility_map')); ?></textarea>
                        <span class="form-hint">GoogleMapで場所を検索後、「共有」→「地図を埋め込む」からiframeコードをコピーしてください。</span>
                    </div>
                    <div class="form-row">
                        <label>施設形態 <span class="required">*</span></label>
                        <div class="taxonomy-select">
                            <?php 
                            $facility_type_terms_for_form = get_terms(array('taxonomy' => 'facility_type','hide_empty' => false));
                            $current_facility_type_slugs_for_form = !empty($saved_common_facility_type) ? $saved_common_facility_type : ($sample_job_id_for_fallback_display ? wp_get_object_terms($sample_job_id_for_fallback_display, 'facility_type', array('fields' => 'slugs')) : array());
                            if ($facility_type_terms_for_form && !is_wp_error($facility_type_terms_for_form)) {
                                foreach ($facility_type_terms_for_form as $term_item_form) {
                                    $checked_facility_attr = (is_array($current_facility_type_slugs_for_form) && in_array($term_item_form->slug, $current_facility_type_slugs_for_form)) ? 'checked' : '';
                                    echo '<label class="radio-label"><input type="radio" name="facility_type[]" value="' . esc_attr($term_item_form->slug) . '" ' . $checked_facility_attr . ' required>' . esc_html($term_item_form->name) . '</label>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="capacity">利用者定員数</label>
                        <input type="text" id="capacity" name="capacity" value="<?php echo esc_attr(get_common_info_value_for_display('capacity')); ?>" placeholder="例: 20名">
                    </div>
                    <div class="form-row">
                        <label for="staff_composition">スタッフ構成</label>
                        <textarea id="staff_composition" name="staff_composition" rows="4" placeholder="例: 児童発達支援管理責任者1名、指導員2名…"><?php echo esc_textarea(get_common_info_value_for_display('staff_composition')); ?></textarea>
                    </div>
                    <div class="form-row">
                        <label for="facility_tel">施設電話番号</label>
                        <input type="text" id="facility_tel" name="facility_tel" value="<?php echo esc_attr(get_common_info_value_for_display('facility_tel')); ?>" placeholder="03-1234-5678">
                    </div>
                    <div class="form-row">
                        <label for="facility_hours">施設営業時間</label>
                        <textarea id="facility_hours" name="facility_hours" rows="3" placeholder="例: 月～金 9:00～18:00"><?php echo esc_textarea(get_common_info_value_for_display('facility_hours')); ?></textarea>
                    </div>
                    <div class="form-row">
                        <label for="facility_url">施設WebサイトURL</label>
                        <input type="url" id="facility_url" name="facility_url" value="<?php echo esc_url(get_common_info_value_for_display('facility_url')); ?>" placeholder="https://example.com">
                    </div>
                </div>
                <div class="form-section">
    <h3 class="form-section-title">職員の声</h3>
    <div class="form-row">
        <label>職員の声</label>
        <div id="common-staff-voice-container">
            <?php 
            // 共通職員の声データを取得
            $common_staff_voice_items = $target_user_id ? get_user_meta($target_user_id, 'common_staff_voice_items', true) : array();
            
            if (!is_array($common_staff_voice_items)) {
                $common_staff_voice_items = array();
            }
            
            // 既存データがない場合はサンプル求人から取得
            if (empty($common_staff_voice_items) && $sample_job_id_for_fallback_display) {
                $sample_staff_voice = get_post_meta($sample_job_id_for_fallback_display, 'staff_voice_items', true);
                if (is_array($sample_staff_voice) && !empty($sample_staff_voice)) {
                    $common_staff_voice_items = $sample_staff_voice;
                }
            }
            
            if (!empty($common_staff_voice_items)) {
                foreach ($common_staff_voice_items as $index => $item) {
                    $image_url = '';
                    if (!empty($item['image_id'])) {
                        $image_url = wp_get_attachment_url($item['image_id']);
                    }
                    ?>
                    <div class="staff-voice-item">
                        <div class="voice-image">
                            <label>サムネイル</label>
                            <div class="voice-image-preview">
                                <?php if (!empty($image_url)): ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="スタッフ画像">
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="staff_voice_image[]" value="<?php echo esc_attr($item['image_id']); ?>">
                            <button type="button" class="upload-voice-image">画像を選択</button>
                            <?php if (!empty($image_url)): ?>
                            <button type="button" class="remove-voice-image">削除</button>
                            <?php else: ?>
                            <button type="button" class="remove-voice-image" style="display:none;">削除</button>
                            <?php endif; ?>
                        </div>
                        <div class="voice-role">
                            <label>職種</label>
                            <input type="text" name="staff_voice_role[]" value="<?php echo esc_attr($item['role']); ?>" placeholder="保育士">
                        </div>
                        <div class="voice-years">
                            <label>勤続年数</label>
                            <input type="text" name="staff_voice_years[]" value="<?php echo esc_attr($item['years']); ?>" placeholder="3年目">
                        </div>
                        <div class="voice-comment">
                            <label>コメント</label>
                            <textarea name="staff_voice_comment[]" rows="4" placeholder="職場の雰囲気や働きやすさについてのコメント"><?php echo esc_textarea($item['comment']); ?></textarea>
                        </div>
                        <?php if ($index > 0): ?>
                        <button type="button" class="remove-voice-item">削除</button>
                        <?php else: ?>
                        <button type="button" class="remove-voice-item" style="display:none;">削除</button>
                        <?php endif; ?>
                    </div>
                    <?php
                }
            } else {
                ?>
                <div class="staff-voice-item">
                    <div class="voice-image">
                        <label>サムネイル</label>
                        <div class="voice-image-preview"></div>
                        <input type="hidden" name="staff_voice_image[]" value="">
                        <button type="button" class="upload-voice-image">画像を選択</button>
                        <button type="button" class="remove-voice-image" style="display:none;">削除</button>
                    </div>
                    <div class="voice-role">
                        <label>職種</label>
                        <input type="text" name="staff_voice_role[]" placeholder="保育士">
                    </div>
                    <div class="voice-years">
                        <label>勤続年数</label>
                        <input type="text" name="staff_voice_years[]" placeholder="3年目">
                    </div>
                    <div class="voice-comment">
                        <label>コメント</label>
                        <textarea name="staff_voice_comment[]" rows="4" placeholder="職場の雰囲気や働きやすさについてのコメント"></textarea>
                    </div>
                    <button type="button" class="remove-voice-item" style="display:none;">削除</button>
                </div>
                <?php
            }
            ?>
        </div>
        <button type="button" id="add-common-voice-item" class="btn-add-item">職員の声を追加</button>
    </div>
</div>

                <div class="form-actions">
                    <input type="submit" name="update_common_info" value="共通情報を更新" class="btn-submit">
                </div>
            </form>
        </div>
    </div>
    <?php elseif (current_user_can('administrator')): ?>
    <div class="admin-notice">
        <p><strong>管理者向け案内：</strong>共通情報を編集するには、上記で対象の加盟教室ユーザーを選択してください。</p>
    </div>
    <?php endif; ?>

    <?php
    // ステータスメッセージの表示
    if (isset($_GET['status'])) {
        $status_msg_get = sanitize_text_field($_GET['status']);
        if ($status_msg_get === 'published') {
            echo '<div class="status-message success">求人を公開しました。</div>';
        } elseif ($status_msg_get === 'drafted') {
            echo '<div class="status-message info">求人を下書きに変更しました。</div>';
        } elseif ($status_msg_get === 'deleted') {
            echo '<div class="status-message warning">求人を削除しました。</div>';
        }
    }
    
    // 求人投稿の取得ロジック
    $job_query_list_args = array(
        'post_type' => 'job',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'pending')
    );
    
    if (current_user_can('administrator')) {
        // 管理者の場合
        if ($target_user_id) {
            // 対象ユーザーが選択されている場合はそのユーザーの求人のみ
            $job_query_list_args['author'] = $target_user_id;
        } else {
            // 対象ユーザーが未選択の場合は全ユーザーの求人を表示
            // author指定なし（全ユーザー）
        }
    } else {
        // 一般ユーザー（agency）の場合は自分の求人のみ
        $job_query_list_args['author'] = $current_user_id;
    }
    
    $job_query_obj = new WP_Query($job_query_list_args);
    
    if ($job_query_obj->have_posts()) :
    ?>

    <div class="job-lis">
        <div class="job-list-header">
            <div class="job-header-item job-title-header">求人タイトル</div>
            <div class="job-header-item job-status-header">ステータス</div>
            <div class="job-header-item job-date-header">最終更新日</div>
            <div class="job-header-item job-actions-header">操作</div>
        </div>
       
        <?php while ($job_query_obj->have_posts()) : $job_query_obj->the_post(); ?>
        <div class="job-list-item">
            <div class="job-item-cell job-title-cell">
                <a href="<?php the_permalink(); ?>" class="job-title-link"><?php the_title(); ?></a>
                <div class="job-taxonomy-info">
                    <?php
                    if (function_exists('get_job_position_display_text')) {
                        $job_pos_text = get_job_position_display_text(get_the_ID());
                        if (!empty($job_pos_text)) echo '<span class="job-position-tag">' . esc_html($job_pos_text) . '</span>';
                    }
                    if (function_exists('get_job_type_display_text')) {
                        $job_type_text_val = get_job_type_display_text(get_the_ID());
                        if (!empty($job_type_text_val)) echo '<span class="job-type-tag">' . esc_html($job_type_text_val) . '</span>';
                    }
                    ?>
                </div>
            </div>
            <div class="job-item-cell job-status-cell">
                <?php
                $post_status_val = get_post_status();
                $status_label_html = '';
                switch ($post_status_val) {
                    case 'publish': $status_label_html = '<span class="status-publish">公開中</span>'; break;
                    case 'draft': $status_label_html = '<span class="status-draft">下書き</span>'; break;
                    case 'pending': $status_label_html = '<span class="status-pending">承認待ち</span>'; break;
                    default: $status_label_html = '<span class="status-other">' . esc_html($post_status_val) . '</span>';
                }
                echo $status_label_html;
                ?>
            </div>
            <div class="job-item-cell job-date-cell"><?php echo get_the_modified_date('Y年m月d日 H:i'); ?></div>
            <div class="job-item-cell job-actions-cell">
                <a href="<?php echo esc_url(home_url('/edit-job/?job_id=' . get_the_ID())); ?>" class="btn-edit">編集</a>
                
                <!-- 複製ボタンを追加 -->
                <button class="btn-duplicate frontend-action" 
                        data-action="duplicate" 
                        data-job-id="<?php echo get_the_ID(); ?>" 
                        data-nonce="<?php echo wp_create_nonce('frontend_job_action'); ?>"
                        title="この求人を複製して新しい求人を作成します">
                    複製
                </button>
                
                <?php if (get_post_status() == 'publish') : ?>
                <button class="btn-draft frontend-action" data-action="draft" data-job-id="<?php echo get_the_ID(); ?>" data-nonce="<?php echo wp_create_nonce('frontend_job_action'); ?>">下書き</button>
                <?php else : ?>
                <button class="btn-publish frontend-action" data-action="publish" data-job-id="<?php echo get_the_ID(); ?>" data-nonce="<?php echo wp_create_nonce('frontend_job_action'); ?>">公開</button>
                <?php endif; ?>
                <button class="btn-delete frontend-action" data-action="delete" data-job-id="<?php echo get_the_ID(); ?>" data-nonce="<?php echo wp_create_nonce('frontend_job_action'); ?>">削除</button>
            </div>
        </div>
        <?php endwhile; ?>
    
    <?php
    else :
        echo '<div class="no-jobs-message">';
        if (current_user_can('administrator') && !$target_user_id) {
            echo '<p>対象ユーザーを選択してください。</p>';
        } else {
            echo '<p>投稿した求人情報はありません。</p>';
            echo '<p><a href="' . esc_url(home_url('/post-job/')) . '" class="btn-new-job">最初の求人を投稿する</a></p>';
        }
        echo '</div>';
    endif;
    wp_reset_postdata();
    ?>
</div>
</div>
<script>
jQuery(document).ready(function($) {
    // アコーディオンの初期設定とクリックイベント
    $('#common-info-content').hide(); 
    $('#common-info-header').on('click', function() {
        var $content = $('#common-info-content');
        var $icon = $(this).find('.accordion-icon');
        $content.slideToggle(300, function() {
            $icon.text($content.is(':visible') ? '▲' : '▼');
        });
        $(this).toggleClass('active');
    });
    
    // --- 勤務地域選択のJavaScript ---
    var initialRegionSlugJS = $('#region-value').val();
    var initialPrefectureSlugJS = $('#prefecture-value').val();
    var initialCitySlugJS = $('#city-value').val();

    // スラッグからタームIDと名前を取得する関数
    function findTermDataBySlugJS(taxonomy, slug, callback) {
        if (!slug) { 
            callback(null, null, null); // ID, Name, Slug
            return; 
        }
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>', 
            type: 'POST', 
            dataType: 'json',
            data: { 
                action: 'get_term_id_by_slug',
                taxonomy: taxonomy, 
                slug: slug, 
                _wpnonce: '<?php echo wp_create_nonce("get_term_id_by_slug_nonce"); ?>' 
            },
            success: function(response) { 
                if (response.success && response.data && response.data.term_id) {
                    callback(response.data.term_id, response.data.name, slug);
                } else {
                    console.warn("Term not found for slug via AJAX: " + slug + (response.data && response.data.message ? " - " + response.data.message : ""));
                    callback(null, null, slug);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) { 
                console.error("AJAX Error in findTermDataBySlugJS for slug: " + slug, textStatus, errorThrown);
                callback(null, null, slug);
            }
        });
    }
    
    // 初期ロード時にプルダウンと表示テキストを設定
    function initializeLocationDropdowns() {
        if (initialRegionSlugJS) {
            findTermDataBySlugJS('job_location', initialRegionSlugJS, function(regionId, regionName, regionSlug) {
                if (regionId) {
                    $('#region-select').val(regionId);
                    loadPrefecturesJS(regionId, initialPrefectureSlugJS, initialCitySlugJS, true);
                } else {
                    if(regionSlug) $('#selected-region-text').text(escapeHtmlJS(regionSlug) + ' (ID未発見)');
                    updateLocationDisplayInFormJS();
                }
                updateSelectedLocationTextJS(regionName, null, null);
            });
        } else {
            updateSelectedLocationTextJS(null, null, null);
            updateLocationDisplayInFormJS();
        }
    }
    
    initializeLocationDropdowns();

    // 地域選択プルダウンの変更イベント
    $('#region-select').on('change', function() {
        var regionId = $(this).val();
        var selectedOption = $(this).find('option:selected');
        var regionSlug = selectedOption.data('slug') || '';
        var regionName = selectedOption.text();

        $('#region-value').val(regionSlug);
        $('#prefecture-value, #city-value').val('');
        $('#prefecture-select, #city-select').html('<option value="">選択してください</option>').prop('disabled', true);
        
        if (regionId) {
            loadPrefecturesJS(regionId, null, null, false);
        }
        updateSelectedLocationTextJS(regionName, null, null);
        updateLocationDisplayInFormJS();
    });

    // 都道府県選択プルダウンの変更イベント
    $('#prefecture-select').on('change', function() {
        var prefectureId = $(this).val();
        var selectedOption = $(this).find('option:selected');
        var prefectureSlug = selectedOption.data('slug') || '';
        var prefectureName = selectedOption.text();
        
        $('#prefecture-value').val(prefectureSlug);
        $('#city-value').val('');
        $('#city-select').html('<option value="">選択してください</option>').prop('disabled', true);
        
        if (prefectureId) {
            loadCitiesJS(prefectureId, null, false);
        }
        updateSelectedLocationTextJS($('#region-select option:selected').text(), prefectureName, null);
        updateLocationDisplayInFormJS();
    });

    // 市区町村選択プルダウンの変更イベント
    $('#city-select').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var citySlug = selectedOption.data('slug') || '';
        var cityName = selectedOption.text();

        $('#city-value').val(citySlug);
        updateSelectedLocationTextJS($('#region-select option:selected').text(), $('#prefecture-select option:selected').text(), cityName);
        updateLocationDisplayInFormJS();
    });

    // 都道府県をロードする関数
    function loadPrefecturesJS(regionId, targetPrefectureSlug, targetCitySlug, isInitialLoad) {
        $('#prefecture-select').prop('disabled', true).html('<option value="">読み込み中...</option>');
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>', type: 'POST', dataType: 'json',
            data: { 
                action: 'get_taxonomy_children', 
                taxonomy: 'job_location', 
                parent_id: regionId, 
                _wpnonce: '<?php echo wp_create_nonce("get_taxonomy_children"); ?>'
            },
            success: function(response) {
                var options = '<option value="">都道府県を選択</option>';
                if (response.success && response.data.length > 0) {
                    $.each(response.data, function(i, term) { 
                        options += '<option value="' + term.term_id + '" data-slug="' + term.slug + '">' + term.name + '</option>'; 
                    });
                    $('#prefecture-select').html(options).prop('disabled', false);
                    
                    if (targetPrefectureSlug) {
                        findTermDataBySlugJS('job_location', targetPrefectureSlug, function(prefId, prefName, prefSlug){
                            if(prefId) {
                                $('#prefecture-select').val(prefId);
                                loadCitiesJS(prefId, targetCitySlug, isInitialLoad);
                            } else if (isInitialLoad) {
                                updateSelectedLocationTextJS($('#region-select option:selected').text(), prefName, null);
                            }
                            updateLocationDisplayInFormJS();
                        });
                    } else if (isInitialLoad) {
                         updateSelectedLocationTextJS($('#region-select option:selected').text(), null, null);
                         updateLocationDisplayInFormJS();
                    }
                } else { 
                    $('#prefecture-select').html('<option value="">都道府県がありません</option>'); 
                    if (isInitialLoad) {
                        updateSelectedLocationTextJS($('#region-select option:selected').text(), null, null);
                        updateLocationDisplayInFormJS();
                    }
                }
            },
            error: function() { 
                $('#prefecture-select').html('<option value="">エラーが発生しました</option>'); 
                if (isInitialLoad) {
                    updateSelectedLocationTextJS($('#region-select option:selected').text(), null, null);
                    updateLocationDisplayInFormJS();
                }
            }
        });
    }

    // 市区町村をロードする関数
    function loadCitiesJS(prefectureId, targetCitySlug, isInitialLoad) {
        $('#city-select').prop('disabled', true).html('<option value="">読み込み中...</option>');
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>', type: 'POST', dataType: 'json',
            data: { 
                action: 'get_taxonomy_children', 
                taxonomy: 'job_location', 
                parent_id: prefectureId, 
                _wpnonce: '<?php echo wp_create_nonce("get_taxonomy_children"); ?>'
            },
            success: function(response) {
                var options = '<option value="">市区町村を選択</option>';
                if (response.success && response.data.length > 0) {
                    $.each(response.data, function(i, term) { 
                        options += '<option value="' + term.term_id + '" data-slug="' + term.slug + '">' + term.name + '</option>'; 
                    });
                    $('#city-select').html(options).prop('disabled', false);
                    if (targetCitySlug) {
                         findTermDataBySlugJS('job_location', targetCitySlug, function(cityId, cityName, citySlug){
                            if(cityId) $('#city-select').val(cityId);
                            updateSelectedLocationTextJS($('#region-select option:selected').text(), $('#prefecture-select option:selected').text(), cityName);
                            updateLocationDisplayInFormJS();
                         });
                    } else if (isInitialLoad) {
                        updateSelectedLocationTextJS($('#region-select option:selected').text(), $('#prefecture-select option:selected').text(), null);
                        updateLocationDisplayInFormJS();
                    }
                } else { 
                    $('#city-select').html('<option value="">市区町村がありません</option>'); 
                    if (isInitialLoad) {
                        updateSelectedLocationTextJS($('#region-select option:selected').text(), $('#prefecture-select option:selected').text(), null);
                        updateLocationDisplayInFormJS();
                    }
                }
            },
            error: function() { 
                $('#city-select').html('<option value="">エラーが発生しました</option>'); 
                if (isInitialLoad) {
                     updateSelectedLocationTextJS($('#region-select option:selected').text(), $('#prefecture-select option:selected').text(), null);
                     updateLocationDisplayInFormJS();
                }
            }
        });
    }
    
    // 「選択中: 」のテキスト表示を更新する関数
    function updateSelectedLocationTextJS(rName, pName, cName) {
        var regionText = (typeof rName === 'string' && rName !== '地域を選択' && rName !== '') ? escapeHtmlJS(rName) : 
                         ($('#region-select option:selected:not([value=""])').length ? escapeHtmlJS($('#region-select option:selected').text()) : '');
        
        var prefectureText = (typeof pName === 'string' && pName !== '都道府県を選択' && pName !== '読み込み中...' && pName !== '都道府県がありません' && pName !== 'エラーが発生しました' && pName !== '') ? escapeHtmlJS(pName) : 
                              ($('#prefecture-select option:selected:not([value=""])').length ? escapeHtmlJS($('#prefecture-select option:selected').text()) : '');
        
        var cityText = (typeof cName === 'string' && cName !== '市区町村を選択' && cName !== '読み込み中...' && cName !== '市区町村がありません' && cName !== 'エラーが発生しました' && cName !== '') ? escapeHtmlJS(cName) : 
                       ($('#city-select option:selected:not([value=""])').length ? escapeHtmlJS($('#city-select option:selected').text()) : '');

        $('#selected-region-text').text(regionText);
        $('#selected-prefecture-text').text(prefectureText ? ' > ' + prefectureText : '');
        $('#selected-city-text').text(cityText ? ' > ' + cityText : '');
    }

    // フォーム内の住所表示（都道府県・市区町村）を更新する関数
    function updateLocationDisplayInFormJS() {
        var currentPrefectureText = $('#prefecture-select option:selected:not([value=""])').text();
        var currentCityText = $('#city-select option:selected:not([value=""])').text();
        var displayHtmlInForm = '';

        if (currentPrefectureText && !currentPrefectureText.match(/選択|読み込み中|ありません|エラー/)) {
            displayHtmlInForm += escapeHtmlJS(currentPrefectureText);
            if (currentCityText && !currentCityText.match(/選択|読み込み中|ありません|エラー/)) {
                displayHtmlInForm += ' ' + escapeHtmlJS(currentCityText);
            }
        }
        
        if(displayHtmlInForm) {
            $('#location_display').text(displayHtmlInForm);
        } else {
            $('#location_display').html('<span class="location-empty">上記で選択した地域が反映されます</span>');
        }
    }

    // HTMLエスケープ用関数
    function escapeHtmlJS(string) {
        if (typeof string !== 'string') return '';
        return string.replace(/[&<>"']/g, function (match) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[match];
        });
    }
    
    // === フロントエンドでのアクション処理（統合版・複製対応） ===
    $(document).off('click', '.frontend-action'); // 既存のイベントハンドラーを削除
    
    $(document).on('click', '.frontend-action', function(e) {
        e.preventDefault();
        var $button = $(this);
        var action = $button.data('action');
        var jobId = $button.data('job-id');
        var nonce = $button.data('nonce'); 

        console.log('Frontend action clicked:', action, 'Job ID:', jobId); // デバッグ用

        // 削除確認
        if (action === 'delete' && !confirm('本当にこの求人を削除しますか？この操作は元に戻せません。')) {
            return;
        }
        
        // 複製確認
        if (action === 'duplicate' && !confirm('この求人を複製しますか？複製した求人は下書き状態で作成され、編集画面が開きます。')) {
            return;
        }
        
        // ボタンのテキストを設定
        var originalText = $button.text();
        var processingText = '処理中...';
        
        switch(action) {
            case 'duplicate':
                processingText = '複製中...';
                break;
            case 'draft':
                processingText = '下書き中...';
                break;
            case 'publish':
                processingText = '公開中...';
                break;
            case 'delete':
                processingText = '削除中...';
                break;
        }
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'frontend_' + action + '_job', 
                job_id: jobId,
                nonce: nonce 
            },
            beforeSend: function() {
                $button.prop('disabled', true).text(processingText);
                console.log('AJAX request started for action:', action); // デバッグ用
            },
            success: function(response) {
                console.log('AJAX response:', response); // デバッグ用
                
                if (response.success) {
                    if (response.data && response.data.redirect) {
                        // 複製の場合は成功メッセージを表示してから編集ページへ
                        if (action === 'duplicate') {
                            alert(response.data.message || '求人を複製しました。編集画面を開きます。');
                        }
                        window.location.href = response.data.redirect; 
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert((response.data && response.data.message) ? response.data.message : 'エラーが発生しました。ページを再読み込みしてください。');
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error:', textStatus, errorThrown, jqXHR.responseText); // デバッグ用
                alert('通信エラーが発生しました: ' + textStatus + '\n詳細: ' + errorThrown);
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // === 職員の声の機能 ===
    
    // 職員の声の追加
    $('#add-common-voice-item').on('click', function() {
        var $container = $('#common-staff-voice-container');
        var $newItem = $container.find('.staff-voice-item:first').clone(true);
        $newItem.find('input, textarea').val('');
        $newItem.find('.voice-image-preview').empty();
        $newItem.find('input[name^="staff_voice_image"]').val('');
        $newItem.find('.remove-voice-image').hide();
        $newItem.find('.remove-voice-item').show();
        $container.append($newItem);
    });
    
    // 職員の声の削除
    $(document).on('click', '.remove-voice-item', function() {
        if ($('#common-staff-voice-container .staff-voice-item').length > 1) {
            $(this).closest('.staff-voice-item').remove();
        } else {
            $(this).closest('.staff-voice-item').find('input, textarea').val('');
            $(this).closest('.staff-voice-item').find('.voice-image-preview').empty();
            $(this).closest('.staff-voice-item').find('input[name^="staff_voice_image"]').val('');
            $(this).closest('.staff-voice-item').find('.remove-voice-image').hide();
        }
    });
    
    // 職員画像のアップロード
    $(document).on('click', '.upload-voice-image', function() {
        var $button = $(this);
        var $item = $button.closest('.staff-voice-item');
        var $previewContainer = $item.find('.voice-image-preview');
        var $inputField = $item.find('input[name^="staff_voice_image"]');
        var $removeButton = $item.find('.remove-voice-image');
        
        var custom_uploader = wp.media({
            title: '職員の声の画像を選択',
            button: {
                text: '画像を選択'
            },
            multiple: false
        });
        
        custom_uploader.on('select', function() {
            var attachment = custom_uploader.state().get('selection').first().toJSON();
            $previewContainer.html('<img src="' + attachment.url + '" alt="スタッフ画像">');
            $inputField.val(attachment.id);
            $removeButton.show();
        });
        
        custom_uploader.open();
    });
    
    // 職員画像の削除
    $(document).on('click', '.remove-voice-image', function() {
        var $item = $(this).closest('.staff-voice-item');
        $item.find('.voice-image-preview').empty();
        $item.find('input[name^="staff_voice_image"]').val('');
        $(this).hide();
    });
});
</script>
<script>
jQuery(document).ready(function($) {
    // 既存のJavaScriptの最後に以下を追加
    
    // 職員の声の追加
    $('#add-common-voice-item').on('click', function() {
        var $container = $('#common-staff-voice-container');
        var $newItem = $container.find('.staff-voice-item:first').clone(true);
        $newItem.find('input, textarea').val('');
        $newItem.find('.voice-image-preview').empty();
        $newItem.find('input[name^="staff_voice_image"]').val('');
        $newItem.find('.remove-voice-image').hide();
        $newItem.find('.remove-voice-item').show();
        $container.append($newItem);
    });
    
    // 職員の声の削除
    $(document).on('click', '.remove-voice-item', function() {
        if ($('#common-staff-voice-container .staff-voice-item').length > 1) {
            $(this).closest('.staff-voice-item').remove();
        } else {
            $(this).closest('.staff-voice-item').find('input, textarea').val('');
            $(this).closest('.staff-voice-item').find('.voice-image-preview').empty();
            $(this).closest('.staff-voice-item').find('input[name^="staff_voice_image"]').val('');
            $(this).closest('.staff-voice-item').find('.remove-voice-image').hide();
        }
    });
    
    // 職員画像のアップロード
    $(document).on('click', '.upload-voice-image', function() {
        var $button = $(this);
        var $item = $button.closest('.staff-voice-item');
        var $previewContainer = $item.find('.voice-image-preview');
        var $inputField = $item.find('input[name^="staff_voice_image"]');
        var $removeButton = $item.find('.remove-voice-image');
        
        var custom_uploader = wp.media({
            title: '職員の声の画像を選択',
            button: {
                text: '画像を選択'
            },
            multiple: false
        });
        
        custom_uploader.on('select', function() {
            var attachment = custom_uploader.state().get('selection').first().toJSON();
            $previewContainer.html('<img src="' + attachment.url + '" alt="スタッフ画像">');
            $inputField.val(attachment.id);
            $removeButton.show();
        });
        
        custom_uploader.open();
    });
    
    // 職員画像の削除
    $(document).on('click', '.remove-voice-image', function() {
        var $item = $(this).closest('.staff-voice-item');
        $item.find('.voice-image-preview').empty();
        $item.find('input[name^="staff_voice_image"]').val('');
        $(this).hide();
    });
});


</script>
<style>
/* 管理者用UIのスタイル */
.admin-user-selector {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 20px;
}

.admin-user-selector h2 {
    color: #856404;
    margin-top: 0;
    margin-bottom: 15px;
}

.user-selector-form {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.user-selector-form label {
    font-weight: 600;
}

.user-selector-form select {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    min-width: 300px;
}

.btn-select-user {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
}

.btn-select-user:hover {
    background-color: #218838;
}

.selected-user-info {
    margin-top: 15px;
    padding: 10px;
    background-color: #d4edda;
    border-radius: 4px;
}

.admin-notice {
    background-color: #d1ecf1;
    border: 1px solid #bee5eb;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 20px;
}

/* アコーディオンのスタイル */
.accordion-header {
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 15px 20px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    margin-bottom: 0;
}

.accordion-header:hover {
    background-color: #e9ecef;
}

.accordion-header .section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0;
    border-bottom: none;
    padding-bottom: 0;
}

.accordion-title {
    flex: 1;
}

.accordion-icon {
    font-size: 14px;
    color: #007cba;
    font-weight: bold;
    transition: transform 0.3s ease;
}

.accordion-header.active .accordion-icon {
    transform: rotate(180deg);
}

.accordion-content {
    border: 1px solid #dee2e6;
    border-top: none;
    border-radius: 0 0 8px 8px;
    background-color: #ffffff;
    padding: 20px;
}

/* 共通情報編集フォームのスタイル */
.common-info-section {
    margin-bottom: 30px;
}

.section-description {
    color: #6c757d;
    margin-bottom: 0;
    margin-top: 5px;
    font-size: 14px;
}

.common-info-form .form-section {
    background-color: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 20px;
}

.form-section-title {
    font-size: 16px;
    color: #495057;
    margin-top: 0;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e0e0e0;
}

.form-row {
    margin-bottom: 15px;
}

.form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
}

.form-row input[type="text"],
.form-row input[type="url"],
.form-row textarea,
.form-row select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    background-color: #fff;
    box-sizing: border-box;
}

.form-row input[type="text"]:focus,
.form-row input[type="url"]:focus,
.form-row textarea:focus,
.form-row select:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-hint {
    display: block;
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

.required {
    color: #dc3545;
}

/* 勤務地域選択のスタイル */
.location-selector {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 15px;
}

.location-level {
    flex: 1;
    min-width: 150px;
}

.location-dropdown {
    width: 100%;
    padding: 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background-color: #fff;
}

.location-dropdown:disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
}

.selected-location-display {
    margin-top: 5px;
    padding: 8px;
    background-color: #e9ecef;
    border-radius: 4px;
    font-size: 14px;
}

.selected-location-display span {
    display: inline-block;
}

/* 住所コンテナのスタイル */
.address-container {
    margin-bottom: 15px;
}

.address-row {
    margin-bottom: 10px;
}

.address-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: normal;
}

#facility_zipcode {
    width: 150px;
}

.location-display {
    padding: 8px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    min-height: 20px;
}

.location-empty {
    color: #6c757d;
    font-style: italic;
}

/* タクソノミー選択のスタイル */
.taxonomy-select {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.radio-label {
    display: inline-block;
    margin: 0;
    padding: 6px 12px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
}

.radio-label:hover {
    background-color: #e9ecef;
}

.radio-label input {
    margin-right: 5px;
}

/* ボタンのスタイル */
.form-actions {
    text-align: center;
    margin-top: 20px;
}

.btn-submit {
    background-color: #007cba;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.btn-submit:hover {
    background-color: #005a87;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .location-selector {
        flex-direction: column;
    }
    
    .location-level {
        width: 100%;
    }
    
    .accordion-header {
        padding: 12px 15px;
    }
    
    .accordion-content {
        padding: 15px;
    }
    
    .common-info-form .form-section {
        padding: 15px;
    }
}
	.btn-duplicate {
    background-color: #17a2b8;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    margin-right: 5px;
    transition: background-color 0.3s;
}

.btn-duplicate:hover {
    background-color: #138496;
}

.btn-duplicate:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
}

/* 操作ボタン全体のレスポンシブ対応を強化 */
.job-actions-cell {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
}

.job-actions-cell .btn-edit,
.job-actions-cell .btn-duplicate,
.job-actions-cell .btn-draft,
.job-actions-cell .btn-publish,
.job-actions-cell .btn-delete {
    font-size: 11px;
    padding: 4px 8px;
    white-space: nowrap;
}

/* スマホ対応：ボタンサイズを小さくして横並びを維持 */
@media (max-width: 768px) {
    .job-actions-cell {
        flex-direction: column;
        gap: 2px;
        align-items: stretch;
    }
    
    .job-actions-cell .btn-edit,
    .job-actions-cell .btn-duplicate,
    .job-actions-cell .btn-draft,
    .job-actions-cell .btn-publish,
    .job-actions-cell .btn-delete {
        width: 100%;
        text-align: center;
        font-size: 10px;
        padding: 3px 6px;
    }
}
</style>

<?php
// 専用のフッターを読み込み
include(get_stylesheet_directory() . '/agency-footer.php');
?>