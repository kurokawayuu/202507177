<?php
/**
 * Template Name: 加盟教室用の求人新規投稿ページ
 * * 新しい求人を投稿するためのページテンプレート
 */

// 専用のヘッダーを読み込み 
include(get_stylesheet_directory() . '/agency-header.php'); 

// ログインチェック
if (!is_user_logged_in()) {
    // 非ログインの場合はログインページにリダイレクト
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

// メディアアップローダーのスクリプトを読み込む
wp_enqueue_media();
wp_enqueue_script('jquery-ui-sortable');

// 現在のユーザー情報を取得
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// ★★★ ユーザーメタから共通情報を取得 ★★★
$common_location_slugs_from_meta = get_user_meta($current_user_id, 'common_job_location_slugs', true);
$common_facility_info_from_meta = get_user_meta($current_user_id, 'common_facility_info', true);
$common_facility_type_from_meta = get_user_meta($current_user_id, 'common_facility_type', true);
$common_full_address_from_meta = get_user_meta($current_user_id, 'common_full_address', true);

// 配列でない場合の初期化
if (!is_array($common_location_slugs_from_meta)) $common_location_slugs_from_meta = array();
if (!is_array($common_facility_info_from_meta)) $common_facility_info_from_meta = array();
if (!is_array($common_facility_type_from_meta)) $common_facility_type_from_meta = array();


// 新規投稿時のフォームで使用する変数の初期化
$contact_info = ''; 

// ユーザーが加盟教室（agency）の権限を持っているかチェック
$is_agency = in_array('agency', $current_user->roles);
if (!$is_agency && !current_user_can('administrator')) {
    // 権限がない場合はエラーメッセージ表示
    echo '<div class="error-message">この機能を利用する権限がありません。</div>';
    get_footer();
    exit;
}

// 新規投稿ページ（page-post-job.php）の正しい構文

// フォーム処理部分の正しい書き方
if (isset($_POST['post_job']) && isset($_POST['job_nonce']) && 
    wp_verify_nonce($_POST['job_nonce'], 'post_new_job')) {
    
    // バリデーションエラーを格納する配列
    $validation_errors = array();
    
    // 1. サムネイル画像の必須チェック
    if (!isset($_POST['thumbnail_ids']) || empty($_POST['thumbnail_ids']) || !is_array($_POST['thumbnail_ids'])) {
        $validation_errors[] = 'サムネイル画像を選択してください。';
    }
    
    // 2. 本文詳細の必須チェック
    $job_content = isset($_POST['job_content']) ? trim(wp_kses_post($_POST['job_content'])) : '';
    $clean_content = strip_tags($job_content);
    if (empty($clean_content) || $clean_content === '' || $job_content === '<p></p>' || $job_content === '<p><br></p>') {
        $validation_errors[] = '本文詳細を入力してください。';
    }
    
    // 3. 職種の必須チェック
    if (!isset($_POST['job_position']) || empty($_POST['job_position']) || !is_array($_POST['job_position'])) {
        $validation_errors[] = '職種を選択してください。';
    } elseif (in_array('other', $_POST['job_position']) && empty(trim($_POST['job_position_other'] ?? ''))) {
        $validation_errors[] = '「その他の職種」を入力してください。';
    }
    
    // 4. 雇用形態の必須チェック
    if (!isset($_POST['job_type']) || empty($_POST['job_type']) || !is_array($_POST['job_type'])) {
        $validation_errors[] = '雇用形態を選択してください。';
    } elseif (in_array('others', $_POST['job_type']) && empty(trim($_POST['job_type_other'] ?? ''))) {
        $validation_errors[] = '「その他の雇用形態」を入力してください。';
    }
    
    // 5. 給与形態の必須チェック
    if (!isset($_POST['salary_form']) || empty($_POST['salary_form'])) {
        $validation_errors[] = '給与形態を選択してください。';
    } else {
        if ($_POST['salary_form'] === 'fixed' && empty(trim($_POST['fixed_salary'] ?? ''))) {
            $validation_errors[] = '給与（固定給）を入力してください。';
        }
        if ($_POST['salary_form'] === 'range') {
            if (empty(trim($_POST['salary_min'] ?? ''))) {
                $validation_errors[] = '給与①最低賃金を入力してください。';
            }
            if (empty(trim($_POST['salary_max'] ?? ''))) {
                $validation_errors[] = '給与②最高賃金を入力してください。';
            }
        }
    }
    
    // バリデーションエラーがある場合は処理を中断
    if (!empty($validation_errors)) {
        $error = implode('<br>', $validation_errors);
    } else {
        // ★★★ バリデーション通過時のみ以下の投稿処理を実行 ★★★
    
    // 基本情報を登録
    $job_data = array(
        'post_title' => sanitize_text_field($_POST['job_title']),
        'post_content' => wp_kses_post($_POST['job_content']),
        'post_status' => 'publish', // デフォルトを 'publish' にする
        'post_type' => 'job',
        'post_author' => $current_user_id
    );
    
    // 投稿を作成
    $job_id = wp_insert_post($job_data);
    
    if (!is_wp_error($job_id)) {
        // ★★★ 共通情報 (ユーザーメタ) を新しい求人に適用 ★★★
        // 勤務地域
        if (!empty($common_location_slugs_from_meta)) {
            wp_set_object_terms($job_id, $common_location_slugs_from_meta, 'job_location');
        }
        
        // 事業所情報
        if (!empty($common_facility_info_from_meta)) {
            foreach ($common_facility_info_from_meta as $key => $value) {
                update_post_meta($job_id, $key, $value);
            }
        }
        // 完全な住所もユーザーメタから
        if (!empty($common_full_address_from_meta)) {
            update_post_meta($job_id, 'facility_address', $common_full_address_from_meta);
        }

        // 施設形態 (POSTで指定されていなければユーザーメタの値を使用)
        if (isset($_POST['facility_type']) && !empty($_POST['facility_type'])) {
            wp_set_object_terms($job_id, $_POST['facility_type'], 'facility_type');
        } elseif (!empty($common_facility_type_from_meta)) {
             wp_set_object_terms($job_id, $common_facility_type_from_meta, 'facility_type');
        }
        // --- ここまで共通情報の適用 ---

        // 運営会社のWebサイトURL保存処理 (これは個別の入力フィールドから)
        if (isset($_POST['company_url'])) {
            update_post_meta($job_id, 'company_url', esc_url_raw($_POST['company_url']));
        }
        
        // 職種（ラジオボタン）の処理
        if (isset($_POST['job_position']) && !empty($_POST['job_position'])) {
            $job_position_value = $_POST['job_position'][0]; 
            if ($job_position_value === 'other' && !empty($_POST['job_position_other'])) {
                $custom_job_position = sanitize_text_field($_POST['job_position_other']);
                update_post_meta($job_id, 'custom_job_position', $custom_job_position);
                wp_set_object_terms($job_id, 'other', 'job_position');
            } else {
                wp_set_object_terms($job_id, $job_position_value, 'job_position');
                delete_post_meta($job_id, 'custom_job_position');
            }
        } else {
            wp_set_object_terms($job_id, array(), 'job_position');
            delete_post_meta($job_id, 'custom_job_position');
        }

        // 雇用形態（ラジオボタン）の処理
        if (isset($_POST['job_type']) && !empty($_POST['job_type'])) {
            $job_type_value = $_POST['job_type'][0]; 
            if ($job_type_value === 'others' && !empty($_POST['job_type_other'])) {
                $custom_job_type = sanitize_text_field($_POST['job_type_other']);
                update_post_meta($job_id, 'custom_job_type', $custom_job_type);
                wp_set_object_terms($job_id, 'others', 'job_type');
            } else {
                wp_set_object_terms($job_id, $job_type_value, 'job_type');
                delete_post_meta($job_id, 'custom_job_type');
            }
        } else {
            wp_set_object_terms($job_id, array(), 'job_type');
            delete_post_meta($job_id, 'custom_job_type');
        }
        
        // 求人特徴（チェックボックス）
        if (isset($_POST['job_feature'])) {
            wp_set_object_terms($job_id, $_POST['job_feature'], 'job_feature');
        } else {
            wp_set_object_terms($job_id, array(), 'job_feature');
        }
        
        // カスタムフィールドの登録
        update_post_meta($job_id, 'job_content_title', sanitize_text_field($_POST['job_content_title']));
        update_post_meta($job_id, 'working_hours', sanitize_text_field($_POST['working_hours']));
        update_post_meta($job_id, 'holidays', sanitize_text_field($_POST['holidays']));
        update_post_meta($job_id, 'benefits', wp_kses_post($_POST['benefits']));
        update_post_meta($job_id, 'requirements', wp_kses_post($_POST['requirements']));
        update_post_meta($job_id, 'application_process', wp_kses_post($_POST['application_process']));
        update_post_meta($job_id, 'contact_info', wp_kses_post($_POST['contact_info']));
        
        // 追加フィールドの登録
        update_post_meta($job_id, 'bonus_raise', wp_kses_post($_POST['bonus_raise']));
        // capacity と staff_composition は共通情報からコピーされる
        
        // 給与情報の登録
        update_post_meta($job_id, 'salary_type', sanitize_text_field($_POST['salary_type']));
        update_post_meta($job_id, 'salary_form', sanitize_text_field($_POST['salary_form']));
        update_post_meta($job_id, 'salary_min', sanitize_text_field($_POST['salary_min']));
        update_post_meta($job_id, 'salary_max', sanitize_text_field($_POST['salary_max']));
        update_post_meta($job_id, 'fixed_salary', sanitize_text_field($_POST['fixed_salary']));
        update_post_meta($job_id, 'salary_remarks', wp_kses_post($_POST['salary_remarks']));

        if (isset($_POST['salary_form'])) { 
            if ($_POST['salary_form'] === 'fixed') {
                $salary_range_recalculated = sanitize_text_field($_POST['fixed_salary']);
            } else {
                $salary_range_recalculated = sanitize_text_field($_POST['salary_min']) . '〜' . sanitize_text_field($_POST['salary_max']);
            }
            update_post_meta($job_id, 'salary_range', $salary_range_recalculated);
        }
        
        // サムネイル画像の処理
        if (isset($_POST['thumbnail_ids']) && is_array($_POST['thumbnail_ids'])) {
            $thumbnail_ids_array = array_map('intval', $_POST['thumbnail_ids']);
            update_post_meta($job_id, 'job_thumbnail_ids', $thumbnail_ids_array);
            if (!empty($thumbnail_ids_array)) {
                set_post_thumbnail($job_id, $thumbnail_ids_array[0]);
            } else {
                delete_post_thumbnail($job_id);
            }
        } else {
            delete_post_meta($job_id, 'job_thumbnail_ids');
            delete_post_thumbnail($job_id);
        }
        
        // 仕事の一日の流れ（配列形式）
        if (isset($_POST['daily_schedule_time']) && is_array($_POST['daily_schedule_time'])) {
            $schedule_items_array = array();
            $schedule_count = count($_POST['daily_schedule_time']);
            for ($i = 0; $i < $schedule_count; $i++) {
                if (!empty($_POST['daily_schedule_time'][$i])) {
                    $schedule_items_array[] = array(
                        'time' => sanitize_text_field($_POST['daily_schedule_time'][$i]),
                        'title' => sanitize_text_field($_POST['daily_schedule_title'][$i]),
                        'description' => wp_kses_post($_POST['daily_schedule_description'][$i])
                    );
                }
            }
            update_post_meta($job_id, 'daily_schedule_items', $schedule_items_array);
        }
        
        // 職員の声（配列形式）
        if (isset($_POST['staff_voice_role']) && is_array($_POST['staff_voice_role'])) {
            $voice_items_array = array();
            $voice_count = count($_POST['staff_voice_role']);
            for ($i = 0; $i < $voice_count; $i++) {
                if (!empty($_POST['staff_voice_role'][$i])) {
                    $voice_items_array[] = array(
                        'image_id' => isset($_POST['staff_voice_image'][$i]) ? intval($_POST['staff_voice_image'][$i]) : 0,
                        'role' => sanitize_text_field($_POST['staff_voice_role'][$i]),
                        'years' => sanitize_text_field($_POST['staff_voice_years'][$i]),
                        'comment' => wp_kses_post($_POST['staff_voice_comment'][$i])
                    );
                }
            }
            update_post_meta($job_id, 'staff_voice_items', $voice_items_array);
        }
        
        $success = true;
        $new_job_url = get_permalink($job_id);
    } else {
        $error_message = $job_id->get_error_message();
        if (empty($error_message)) {
            $error_message = '不明なエラーが発生しました。再度お試しください。';
        }
        $error = $error_message;
    }
}
}
?>

<div class="post-job-container">
    <h1 class="page-title">新しい求人を投稿</h1>
    
    <?php if (isset($success) && $success && isset($new_job_url)): ?>
    <div class="success-message">
        <p>求人情報を投稿しました。</p>
        <p>
            <a href="<?php echo esc_url($new_job_url); ?>" class="btn-view">投稿した求人を確認する</a>
            <a href="<?php echo esc_url(get_permalink()); ?>" class="btn-new">別の求人を投稿する</a>
            <?php
            if (isset($job_id)) { 
                $draft_button_url = admin_url('admin-post.php?action=draft_job&job_id=' . $job_id . '&_wpnonce=' . wp_create_nonce('draft_job_' . $job_id));
                ?>
                <a href="<?php echo esc_url($draft_button_url); ?>" class="btn-draft">下書きにする</a>
            <?php } ?>
        </p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error) && !empty($error)): ?>
    <div class="error-message">
        <p>エラーが発生しました: <?php echo esc_html($error); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!isset($success) || !$success): ?>
    <form method="post" class="post-job-form" enctype="multipart/form-data">
        <?php wp_nonce_field('post_new_job', 'job_nonce'); ?>
        
        <div class="form-section">
            <h2 class="secti-title">基本情報</h2>
            
            <div class="form-row">
                <label for="job_title">求人タイトル <span class="required">*</span></label>
                <input type="text" id="job_title" name="job_title" value="<?php echo isset($_POST['job_title']) ? esc_attr($_POST['job_title']) : ''; ?>" required><span class="form-hint">【教室名】＋【募集職種】＋【雇用形態】＋【特徴】を書くことをお勧めします。<br>例：こどもプラス○○駅前教室　放課後等デイサービス指導員　パート／アルバイト　週3日～OK</span>
            </div>
            
            <div class="form-row">
                <label>サムネイル画像 <span class="required">*</span></label>
                <div id="thumbnails-container"></div>
                <button type="button" class="btn-media-upload" id="upload_thumbnails">画像を追加</button>
                <p class="form-hint">スライドで複数画像が掲載可能です。画像の順番はドラッグ&ドロップで変更できます。</p>
            </div>
            
            <div class="form-row">
                <label for="job_content_title">本文タイトル <span class="required">*</span><span class="required">※系列教室などで使いまわしＮＧ！かぶらないようにしてください。</span></label>
                <input type="text" id="job_content_title" name="job_content_title" value="<?php echo isset($_POST['job_content_title']) ? esc_attr($_POST['job_content_title']) : ''; ?>" required><span class="form-hint">何を説明するか一目でわかる短文に。全角15文字程度が目安。<br>例：週休2日制・残業ほぼなし！児童発達支援管理責任者として活躍しませんか？</span>
            </div>
            
            <div class="form-row">
                <label for="job_content">本文詳細 <span class="required">*</span><span class="required">※系列教室などで使いまわしＮＧ！かぶらないようにしてください。</span></label>
                <?php 
                $job_content_editor_val = isset($_POST['job_content']) ? wp_kses_post($_POST['job_content']) : '';
                wp_editor($job_content_editor_val, 'job_content', array(
                    'media_buttons' => true,
                    'textarea_name' => 'job_content',
                    'textarea_rows' => 10
                )); 
                ?>
                <span class="form-hint">仕事内容の詳細な説明や特徴などを入力してください。</span>
            </div>
        </div>

        <div class="form-section">
            <h2 class="secti-title">募集内容</h2>
            
            <div class="form-row">
                <label>勤務地域</label>
                <div class="readonly-field">
                    <?php
                    // ★★★ ユーザーメタから勤務地域情報を表示 ★★★
                    $location_display_text = '';
                    if (!empty($common_location_slugs_from_meta)) {
                        $region_name_display = ''; $prefecture_name_display = ''; $city_name_display = '';
                        if (isset($common_location_slugs_from_meta[0])) {
                            $term = get_term_by('slug', $common_location_slugs_from_meta[0], 'job_location');
                            if ($term && !is_wp_error($term)) $region_name_display = $term->name;
                        }
                        if (isset($common_location_slugs_from_meta[1])) {
                            $term = get_term_by('slug', $common_location_slugs_from_meta[1], 'job_location');
                            if ($term && !is_wp_error($term)) $prefecture_name_display = $term->name;
                        }
                        if (isset($common_location_slugs_from_meta[2])) {
                            $term = get_term_by('slug', $common_location_slugs_from_meta[2], 'job_location');
                            if ($term && !is_wp_error($term)) $city_name_display = $term->name;
                        }
                        
                        $location_parts_display = array_filter([$region_name_display, $prefecture_name_display, $city_name_display]);
                        $location_display_text = implode(' > ', $location_parts_display);
                    }
                    echo '<p class="readonly-value">' . (empty($location_display_text) ? '共通情報が未設定です' : esc_html($location_display_text)) . '</p>';
                    ?>
                    <p class="form-hint">※ 勤務地域の設定は「求人情報管理」ページの共通情報編集で行えます。ここで表示されている地域情報が新しい求人に適用されます。</p>
                </div>
            </div>
            
            <div class="form-row">
                <label>職種 <span class="required">*</span></label>
                <div class="taxonomy-select">
                    <?php 
                    $job_position_terms_form_val = get_terms(array('taxonomy' => 'job_position', 'hide_empty' => false));
                    if ($job_position_terms_form_val && !is_wp_error($job_position_terms_form_val)) {
                        foreach ($job_position_terms_form_val as $term_form_item) {
                            $checked_attr_val = (isset($_POST['job_position']) && is_array($_POST['job_position']) && in_array($term_form_item->slug, $_POST['job_position'])) ? 'checked' : '';
                            echo '<label class="radio-label"><input type="radio" name="job_position[]" value="' . esc_attr($term_form_item->slug) . '" ' . $checked_attr_val . ' required>' . esc_html($term_form_item->name) . '</label>';
                        }
                    }
                    ?>
                </div>
                <div id="job-position-other-field" class="other-input-field" style="display: <?php echo (isset($_POST['job_position']) && is_array($_POST['job_position']) && in_array('other', $_POST['job_position'])) ? 'block' : 'none'; ?>;">
                    <label for="job_position_other">その他の職種を入力 <span class="required">*</span></label>
                    <input type="text" id="job_position_other" name="job_position_other" value="<?php echo isset($_POST['job_position_other']) ? esc_attr($_POST['job_position_other']) : ''; ?>" placeholder="具体的な職種名を入力してください">
                </div>
            </div>
            
            <div class="form-row">
                <label>雇用形態 <span class="required">*</span></label>
                <div class="taxonomy-select">
                    <?php 
                    $job_type_terms_form_val = get_terms(array('taxonomy' => 'job_type', 'hide_empty' => false));
                    if ($job_type_terms_form_val && !is_wp_error($job_type_terms_form_val)) {
                        foreach ($job_type_terms_form_val as $term_form_item) {
                            $checked_attr_val = (isset($_POST['job_type']) && is_array($_POST['job_type']) && in_array($term_form_item->slug, $_POST['job_type'])) ? 'checked' : '';
                            echo '<label class="radio-label"><input type="radio" name="job_type[]" value="' . esc_attr($term_form_item->slug) . '" ' . $checked_attr_val . ' required>' . esc_html($term_form_item->name) . '</label>';
                        }
                    }
                    ?>
                </div>
                 <div id="job-type-other-field" class="other-input-field" style="display: <?php echo (isset($_POST['job_type']) && is_array($_POST['job_type']) && in_array('others', $_POST['job_type'])) ? 'block' : 'none'; ?>;">
                    <label for="job_type_other">その他の雇用形態を入力 <span class="required">*</span></label>
                    <input type="text" id="job_type_other" name="job_type_other" value="<?php echo isset($_POST['job_type_other']) ? esc_attr($_POST['job_type_other']) : ''; ?>" placeholder="具体的な雇用形態を入力してください">
                </div>
            </div>
            
            <div class="form-row">
                <label for="contact_info">仕事内容 <span class="required">*</span></label>
                <textarea id="contact_info" name="contact_info" rows="5" required><?php echo isset($_POST['contact_info']) ? esc_textarea($_POST['contact_info']) : $contact_info; ?></textarea>
                <span class="form-hint">具体的な業務内容や仕事の特徴など</span>
            </div>

            <div class="form-row">
                <label for="requirements">応募要件 <span class="required">*</span></label>
                <textarea id="requirements" name="requirements" rows="5" required><?php echo isset($_POST['requirements']) ? esc_textarea($_POST['requirements']) : ''; ?></textarea>
                <span class="form-hint">必要な資格や経験など</span>
            </div>
            
            <div class="form-row">
                <label for="working_hours">勤務時間 <span class="required">*</span></label>
                <textarea id="working_hours" name="working_hours" rows="3" required><?php echo isset($_POST['working_hours']) ? esc_textarea($_POST['working_hours']) : ''; ?></textarea>
                <span class="form-hint">例: 9:00〜18:00（休憩60分）</span>
            </div>
            
            <div class="form-row">
                <label for="holidays">休日・休暇 <span class="required">*</span></label>
                <textarea id="holidays" name="holidays" rows="3" required><?php echo isset($_POST['holidays']) ? esc_textarea($_POST['holidays']) : ''; ?></textarea>
                <span class="form-hint">例: 土日祝、年末年始、有給休暇あり</span>
            </div>
            
            <div class="form-row">
                <label for="benefits">福利厚生 <span class="required">*</span></label>
                <textarea id="benefits" name="benefits" rows="5" required><?php echo isset($_POST['benefits']) ? esc_textarea($_POST['benefits']) : ''; ?></textarea>
                <span class="form-hint">社会保険、交通費支給、各種手当など</span>
            </div>
            
            <div class="form-row">
                <label for="salary_type">賃金形態 <span class="required">*</span></label>
                <select id="salary_type" name="salary_type" required>
                    <option value="MONTH" <?php selected(isset($_POST['salary_type']) ? $_POST['salary_type'] : 'MONTH', 'MONTH'); ?>>月給</option>
                    <option value="HOUR" <?php selected(isset($_POST['salary_type']) ? $_POST['salary_type'] : '', 'HOUR'); ?>>時給</option>
                </select>
            </div>
            
            <div class="form-row">
                <label>給与形態 <span class="required">*</span></label>
                <div class="radio-wrapper">
                    <label>
                        <input type="radio" name="salary_form" value="fixed" <?php checked(isset($_POST['salary_form']) ? $_POST['salary_form'] : '', 'fixed'); ?> required> 
                        給与に幅がない（固定給）
                    </label>
                    <label>
                        <input type="radio" name="salary_form" value="range" <?php checked(isset($_POST['salary_form']) ? $_POST['salary_form'] : 'range', 'range'); ?> required> 
                        給与に幅がある（範囲給）
                    </label>
                </div>
            </div>
            
            <div id="fixed-salary-field" class="form-row salary-field" style="display: none;">
                <label for="fixed_salary">給与（固定給） <span class="required">*</span><span class="required">※半角数字のみカンマ「,」や「円」は無し</span></label>
                <input type="text" id="fixed_salary" name="fixed_salary" value="<?php echo isset($_POST['fixed_salary']) ? esc_attr($_POST['fixed_salary']) : ''; ?>">
                <span class="form-hint">例: 250000</span>
            </div>
            
            <div id="range-salary-fields" class="salary-field" style="display: none;">
                <div class="form-row">
                    <label for="salary_min">給与①最低賃金 <span class="required">*</span><span class="required">※半角数字のみカンマ「,」や「円」は無し</span></label>
                    <input type="text" id="salary_min" name="salary_min" value="<?php echo isset($_POST['salary_min']) ? esc_attr($_POST['salary_min']) : ''; ?>">
                    <span class="form-hint">例: 200000</span>
                </div>
                <div class="form-row">
                    <label for="salary_max">給与②最高賃金 <span class="required">*</span><span class="required">※半角数字のみカンマ「,」や「円」は無し</span></label>
                    <input type="text" id="salary_max" name="salary_max" value="<?php echo isset($_POST['salary_max']) ? esc_attr($_POST['salary_max']) : ''; ?>">
                    <span class="form-hint">例: 300000</span>
                </div>
            </div>
            
            <div class="form-row">
                <label for="salary_remarks">給料についての備考</label>
                <textarea id="salary_remarks" name="salary_remarks" rows="3"><?php echo isset($_POST['salary_remarks']) ? esc_textarea($_POST['salary_remarks']) : ''; ?></textarea>
                <span class="form-hint">例: 経験・能力により優遇。試用期間3ヶ月あり（同条件）。</span>
            </div>
            
            <input type="hidden" id="salary_range" name="salary_range" value="<?php echo isset($_POST['salary_range']) ? esc_attr($_POST['salary_range']) : ''; ?>">

            <div class="form-row">
                <label for="bonus_raise">昇給・賞与</label>
                <textarea id="bonus_raise" name="bonus_raise" rows="5"><?php echo isset($_POST['bonus_raise']) ? esc_textarea($_POST['bonus_raise']) : ''; ?></textarea>
                <span class="form-hint">昇給制度や賞与の詳細など</span>
            </div>
            
            <div class="form-row">
                <label for="application_process">選考プロセス</label>
                <textarea id="application_process" name="application_process" rows="5"><?php echo isset($_POST['application_process']) ? esc_textarea($_POST['application_process']) : ''; ?></textarea>
                <span class="form-hint">書類選考、面接回数など</span>
            </div>
        </div>
        
        <div class="form-section">
            <h2 class="secti-title">求人の特徴</h2>
            <?php 
            $parent_feature_terms_form_val = get_terms(array('taxonomy' => 'job_feature', 'hide_empty' => false, 'parent' => 0));
            if ($parent_feature_terms_form_val && !is_wp_error($parent_feature_terms_form_val)) {
                echo '<div class="feature-accordion-container">';
                foreach ($parent_feature_terms_form_val as $parent_term_form_item) {
                    echo '<div class="feature-accordion"><div class="feature-accordion-header"><h3>' . esc_html($parent_term_form_item->name) . '</h3><span class="accordion-icon">+</span></div>';
                    $child_feature_terms_form_val = get_terms(array('taxonomy' => 'job_feature', 'hide_empty' => false, 'parent' => $parent_term_form_item->term_id));
                    if ($child_feature_terms_form_val && !is_wp_error($child_feature_terms_form_val)) {
                        echo '<div class="feature-accordion-content" style="display:none;"><div class="taxonomy-select">';
                        foreach ($child_feature_terms_form_val as $term_form_item) {
                            $checked_attr_val = (isset($_POST['job_feature']) && is_array($_POST['job_feature']) && in_array($term_form_item->slug, $_POST['job_feature'])) ? 'checked' : '';
                            echo '<label class="checkbox-label feature-label"><input type="checkbox" name="job_feature[]" value="' . esc_attr($term_form_item->slug) . '" ' . $checked_attr_val . '>' . esc_html($term_form_item->name) . '</label>';
                        }
                        echo '</div></div>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="form-section">
            <h2 class="secti-title">職場の環境</h2>
            <div class="form-row">
                <label>仕事の一日の流れ</label>
                <div id="daily-schedule-container">
                    <?php
                    $daily_schedule_time_post_val = isset($_POST['daily_schedule_time']) && is_array($_POST['daily_schedule_time']) ? $_POST['daily_schedule_time'] : array('');
                    $daily_schedule_title_post_val = isset($_POST['daily_schedule_title']) && is_array($_POST['daily_schedule_title']) ? $_POST['daily_schedule_title'] : array('');
                    $daily_schedule_description_post_val = isset($_POST['daily_schedule_description']) && is_array($_POST['daily_schedule_description']) ? $_POST['daily_schedule_description'] : array('');
                    $schedule_item_count_val = max(count($daily_schedule_time_post_val), 1); 

                    for ($i = 0; $i < $schedule_item_count_val; $i++):
                    ?>
                    <div class="daily-schedule-item">
                        <div class="schedule-time"><label>時間</label><input type="text" name="daily_schedule_time[]" value="<?php echo esc_attr($daily_schedule_time_post_val[$i] ?? ''); ?>" placeholder="9:00"></div>
                        <div class="schedule-title"><label>タイトル</label><input type="text" name="daily_schedule_title[]" value="<?php echo esc_attr($daily_schedule_title_post_val[$i] ?? ''); ?>" placeholder="出社・朝礼"></div>
                        <div class="schedule-description"><label>詳細</label><textarea name="daily_schedule_description[]" rows="3" placeholder="業務準備、1日の予定確認など"><?php echo esc_textarea($daily_schedule_description_post_val[$i] ?? ''); ?></textarea></div>
                        <button type="button" class="remove-schedule-item" style="<?php echo ($i === 0 && $schedule_item_count_val === 1) ? 'display:none;' : ''; ?>">削除</button>
                    </div>
                    <?php endfor; ?>
                </div>
                <button type="button" id="add-schedule-item" class="btn-add-item">時間枠を追加</button>
            </div>
            
            
        </div>
        
        <div class="form-section">
            <h2 class="secti-title">事業所の情報</h2>
            <p class="section-note">※ 事業所情報の設定は「求人情報管理」ページの共通情報編集で行えます。ここで表示されている情報が新しい求人に適用されます。</p>
            <div class="readonly-info-grid">
                <?php 
                // ★★★ ユーザーメタから事業所情報を表示 ★★★
                $display_facility_name_val = $common_facility_info_from_meta['facility_name'] ?? '';
                if (!empty($display_facility_name_val)): 
                    $display_facility_company_val = $common_facility_info_from_meta['facility_company'] ?? '未設定';
                    $display_company_url_val = $common_facility_info_from_meta['company_url'] ?? '';
                    $display_facility_address_val = $common_full_address_from_meta ?? '未設定'; // ユーザーメタから取得
                    $display_facility_map_val = $common_facility_info_from_meta['facility_map'] ?? '';
                    $display_capacity_val = $common_facility_info_from_meta['capacity'] ?? '未設定';
                    $display_staff_composition_val = $common_facility_info_from_meta['staff_composition'] ?? '未設定';
                    $display_facility_tel_val = $common_facility_info_from_meta['facility_tel'] ?? '未設定';
                    $display_facility_hours_val = $common_facility_info_from_meta['facility_hours'] ?? '未設定';
                    $display_facility_url_val = $common_facility_info_from_meta['facility_url'] ?? '';

                    // 施設形態の表示
                    $facility_type_names_display = array();
                    if (!empty($common_facility_type_from_meta)) {
                        foreach($common_facility_type_from_meta as $slug) {
                            $term = get_term_by('slug', $slug, 'facility_type');
                            if ($term && !is_wp_error($term)) $facility_type_names_display[] = $term->name;
                        }
                    }
                    $display_facility_type_text_val = !empty($facility_type_names_display) ? implode(', ', $facility_type_names_display) : '未設定';
                ?>
                <div class="readonly-item"><label>施設名</label><div class="readonly-value"><?php echo esc_html($display_facility_name_val); ?></div></div>
                <div class="readonly-item"><label>運営会社名</label><div class="readonly-value"><?php echo esc_html($display_facility_company_val); ?></div></div>
                <div class="readonly-item"><label>運営会社のWebサイトURL</label><div class="readonly-value"><?php if ($display_company_url_val): ?><a href="<?php echo esc_url($display_company_url_val); ?>" target="_blank"><?php echo esc_html($display_company_url_val); ?></a><?php else: ?>未設定<?php endif; ?></div></div>
                <div class="readonly-item"><label>施設住所</label><div class="readonly-value"><?php echo esc_html($display_facility_address_val); ?></div></div>
                <div class="readonly-item"><label>GoogleMap</label><div class="readonly-value"><?php if ($display_facility_map_val): ?><div class="map-preview"><?php echo $display_facility_map_val; /* 保存時にwp_kses済み */ ?></div><?php else: ?>未設定<?php endif; ?></div></div>
                <div class="readonly-item"><label>施設形態</label><div class="readonly-value"><?php echo esc_html($display_facility_type_text_val); ?></div></div>
                <div class="readonly-item"><label>利用者定員数</label><div class="readonly-value"><?php echo esc_html($display_capacity_val); ?></div></div>
                <div class="readonly-item"><label>スタッフ構成</label><div class="readonly-value"><?php echo wp_kses_post($display_staff_composition_val); ?></div></div>
                <div class="readonly-item"><label>施設電話番号</label><div class="readonly-value"><?php echo esc_html($display_facility_tel_val); ?></div></div>
                <div class="readonly-item"><label>施設営業時間</label><div class="readonly-value"><?php echo esc_html($display_facility_hours_val); ?></div></div>
                <div class="readonly-item"><label>施設WebサイトURL</label><div class="readonly-value"><?php if ($display_facility_url_val): ?><a href="<?php echo esc_url($display_facility_url_val); ?>" target="_blank"><?php echo esc_html($display_facility_url_val); ?></a><?php else: ?>未設定<?php endif; ?></div></div>
                <?php else: ?>
                <div class="readonly-item"><label>事業所情報</label><div class="readonly-value">共通情報が未設定です。「求人情報管理」ページで設定してください。</div></div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="form-actions">
            <input type="submit" name="post_job" value="求人情報を投稿する" class="btn-submit">
            <a href="<?php echo esc_url(home_url('/job-list/')); // 求人一覧ページへ ?>" class="btn-cancel">キャンセル</a>
        </div>
    </form>
    <?php endif; ?>
    
    <script>
    jQuery(document).ready(function($) {
        $('#upload_thumbnails').click(function(e) {
            e.preventDefault();
            var custom_uploader = wp.media({title: '求人サムネイル画像を選択',button: {text: '画像を選択'},multiple: true});
            custom_uploader.on('select', function() {
                var attachments = custom_uploader.state().get('selection').toJSON();
                $.each(attachments, function(index, attachment) {
                    var $thumbnailItem = $('<div class="thumbnail-item"><div class="thumbnail-preview"><img src="' + attachment.url + '" alt="サムネイル画像"></div><input type="hidden" name="thumbnail_ids[]" value="' + attachment.id + '"><button type="button" class="remove-thumbnail-btn">削除</button></div>');
                    $('#thumbnails-container').append($thumbnailItem);
                });
            });
            custom_uploader.open();
        });
        $(document).on('click', '.remove-thumbnail-btn', function() { $(this).closest('.thumbnail-item').remove(); });
        if ($.fn.sortable) { $('#thumbnails-container').sortable({placeholder: 'ui-state-highlight'}); $('#thumbnails-container').disableSelection(); }
        
        $('input[name="salary_form"]').on('change', function() {
            $('.salary-field').hide();
            $('#fixed_salary, #salary_min, #salary_max').prop('required', false);
            if ($(this).val() === 'fixed') {
                $('#fixed-salary-field').show();
                $('#fixed_salary').prop('required', true);
            } else if ($(this).val() === 'range') {
                $('#range-salary-fields').show();
                $('#salary_min, #salary_max').prop('required', true);
            }
        });
        if (!$('input[name="salary_form"]:checked').length) {
             $('input[name="salary_form"][value="range"]').prop('checked', true);
        }
        $('input[name="salary_form"]:checked').trigger('change');
        
        $('.feature-accordion-header').on('click', function() {
            var $content = $(this).next('.feature-accordion-content');
            var $icon = $(this).find('.accordion-icon');
            $content.slideToggle(function() { $icon.text($content.is(':visible') ? '-' : '+'); });
        });
        $('.feature-accordion').each(function() {
            var $content = $(this).find('.feature-accordion-content');
            var $icon = $(this).find('.accordion-icon');
            if ($(this).find('input:checked').length > 0) { $content.show(); $icon.text('-'); }
             else { $content.hide(); $icon.text('+');}
        });

        $('#add-schedule-item').on('click', function() {
            var $container = $('#daily-schedule-container');
            var $newItem = $container.find('.daily-schedule-item:first').clone(true);
            $newItem.find('input, textarea').val('');
            $newItem.find('.remove-schedule-item').show();
            $container.append($newItem);
        });
        $(document).on('click', '.remove-schedule-item', function() {
            if ($('#daily-schedule-container .daily-schedule-item').length > 1) {
                $(this).closest('.daily-schedule-item').remove();
            } else { $(this).closest('.daily-schedule-item').find('input, textarea').val(''); }
        });

        $('#add-voice-item').on('click', function() {
            var $container = $('#staff-voice-container');
            var $newItem = $container.find('.staff-voice-item:first').clone(true);
            $newItem.find('input, textarea').val('');
            $newItem.find('.voice-image-preview').empty();
            $newItem.find('input[name^="staff_voice_image"]').val('');
            $newItem.find('.remove-voice-image').hide();
            $newItem.find('.remove-voice-item').show();
            $container.append($newItem);
        });
        $(document).on('click', '.remove-voice-item', function() {
            if ($('#staff-voice-container .staff-voice-item').length > 1) {
                $(this).closest('.staff-voice-item').remove();
            } else { 
                $(this).closest('.staff-voice-item').find('input, textarea').val('');
                $(this).closest('.staff-voice-item').find('.voice-image-preview').empty();
                $(this).closest('.staff-voice-item').find('input[name^="staff_voice_image"]').val('');
                $(this).closest('.staff-voice-item').find('.remove-voice-image').hide();
            }
        });

        $(document).on('click', '.upload-voice-image', function() {
            var $button = $(this);
            var $item = $button.closest('.staff-voice-item');
            var custom_uploader = wp.media({title: '職員の声の画像を選択',button: {text: '画像を選択'},multiple: false});
            custom_uploader.on('select', function() {
                var attachment = custom_uploader.state().get('selection').first().toJSON();
                $item.find('.voice-image-preview').html('<img src="' + attachment.url + '" alt="スタッフ画像">');
                $item.find('input[name^="staff_voice_image[]"]').val(attachment.id);
                $item.find('.remove-voice-image').show();
            });
            custom_uploader.open();
        });
        $(document).on('click', '.remove-voice-image', function() {
            var $item = $(this).closest('.staff-voice-item');
            $item.find('.voice-image-preview').empty();
            $item.find('input[name^="staff_voice_image[]"]').val('');
            $(this).hide();
        });

        $('input[name="job_position[]"]').on('change', function() {
            var $otherField = $('#job-position-other-field');
            var $otherInput = $('#job_position_other');
            if ($(this).val() === 'other' && $(this).is(':checked')) {
                $otherField.show(); $otherInput.prop('required', true);
            } else { $otherField.hide(); $otherInput.prop('required', false).val(''); }
        });
        $('input[name="job_type[]"]').on('change', function() {
            var $otherField = $('#job-type-other-field');
            var $otherInput = $('#job_type_other');
            if ($(this).val() === 'others' && $(this).is(':checked')) {
                $otherField.show(); $otherInput.prop('required', true);
            } else { $otherField.hide(); $otherInput.prop('required', false).val(''); }
        });
        $('input[name="job_position[]"]:checked').trigger('change');
        $('input[name="job_type[]"]:checked').trigger('change');

        $('.post-job-form').on('submit', function(e) {
    let formIsValid = true;
    let errorMessages = [];

    // 1. サムネイル画像の必須チェック
    var thumbnailCount = $('input[name="thumbnail_ids[]"]').length;
    if (thumbnailCount === 0) {
        errorMessages.push('サムネイル画像を選択してください。');
        formIsValid = false;
    }

    // 2. 本文詳細の必須チェック
    var editorContent = '';
    if (typeof tinyMCE !== 'undefined' && tinyMCE.get('job_content')) {
        editorContent = tinyMCE.get('job_content').getContent();
    } else {
        editorContent = $('textarea[name="job_content"]').val() || '';
    }
    var cleanContent = editorContent.replace(/<[^>]*>/g, '').trim();
    if (!cleanContent || cleanContent === '') {
        errorMessages.push('本文詳細を入力してください。');
        formIsValid = false;
    }
            if ($('input[name="job_position[]"]:checked').length === 0) {
                alert('職種を選択してください。'); formIsValid = false;
            } else if ($('input[name="job_position[]"][value="other"]:checked').length > 0 && $('#job_position_other').val().trim() === '') {
                alert('「その他の職種」を入力してください。'); $('#job_position_other').focus(); formIsValid = false;
            }
            if ($('input[name="job_type[]"]:checked').length === 0) {
                alert('雇用形態を選択してください。'); formIsValid = false;
            } else if ($('input[name="job_type[]"][value="others"]:checked').length > 0 && $('#job_type_other').val().trim() === '') {
                alert('「その他の雇用形態」を入力してください。'); $('#job_type_other').focus(); formIsValid = false;
            }
            if ($('input[name="salary_form"]:checked').length === 0) {
                alert('給与形態を選択してください。'); formIsValid = false;
            } else {
                if ($('input[name="salary_form"][value="fixed"]:checked').length > 0 && $('#fixed_salary').val().trim() === '') {
                     alert('給与（固定給）を入力してください。'); $('#fixed_salary').focus(); formIsValid = false;
                }
                if ($('input[name="salary_form"][value="range"]:checked').length > 0) {
                    if ($('#salary_min').val().trim() === '') { alert('給与①最低賃金を入力してください。'); $('#salary_min').focus(); formIsValid = false; }
                    if ($('#salary_max').val().trim() === '') { alert('給与②最高賃金を入力してください。'); $('#salary_max').focus(); formIsValid = false; }
                }
            }
             if (!formIsValid) {
        e.preventDefault();
        alert('以下の項目を確認してください：\n\n' + errorMessages.join('\n'));
        return false;
    }
});
    });
    </script>
    
   
    <style>
    /* 複数サムネイルのスタイル */
    #thumbnails-container {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .thumbnail-item {
        position: relative;
        width: 150px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px;
        background: #f9f9f9;
    }
    
    .thumbnail-preview {
        width: 100%;
        height: 120px;
        margin-bottom: 8px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
		
		.other-input-field {
    margin-top: 15px;
    padding: 15px;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.other-input-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #495057;
}

.other-input-field input[type="text"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    background-color: #fff;
}

.other-input-field input[type="text"]:focus {
    border-color: #80bdff;
    outline: 0;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.other-input-field .required {
    color: #dc3545;
}
    
    .thumbnail-preview img {
        max-width: 100%;
        max-height: 120px;
        object-fit: contain;
    }
    
    .remove-thumbnail-btn {
        width: 100%;
        padding: 4px;
        background-color: #f44336;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
        font-size: 12px;
    }
    
    .remove-thumbnail-btn:hover {
        background-color: #d32f2f;
    }
    
    /* ドラッグ&ドロップ用のスタイル */
    .ui-state-highlight {
        width: 150px;
        height: 165px;
        border: 2px dashed #2196F3;
        background-color: #E3F2FD;
    }
    
    /* コンパクトな勤務地域選択のスタイル */
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
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: #fff;
    }
    
    .location-dropdown:disabled {
        background-color: #f5f5f5;
        cursor: not-allowed;
    }
    
    .selected-location-display {
        margin-top: 5px;
        padding: 8px;
        background-color: #f5f5f5;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .selected-location-display span {
        display: inline-block;
    }
    
    /* 特徴タグのアコーディオンスタイル */
    .feature-accordion-container {
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .feature-accordion {
        margin-bottom: 1px;
    }
    
    .feature-accordion:last-child {
        margin-bottom: 0;
    }
    
    .feature-accordion-header {
        background-color: #f7f7f7;
        padding: 10px 15px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .feature-accordion-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 500;
    }
    
    .accordion-icon {
        font-size: 18px;
        font-weight: bold;
    }
    
    .feature-accordion-content {
        padding: 15px;
        background-color: #fff;
        display: none;
    }
    
    /* チェックボックスのスタイル強化 */
    .feature-accordion .taxonomy-select {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .feature-accordion .checkbox-label {
        flex: 0 0 auto;
        margin: 0;
    }

    /* ラジオボタンのスタイル */
    .radio-label {
        display: inline-block;
        margin: 5px;
        padding: 6px 12px;
        background-color: #f5f5f5;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .radio-label input {
        margin-right: 5px;
    }
    
    /* 下書きにするボタン */
    .btn-draft {
        display: inline-block;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 14px;
        margin-right: 10px;
        background-color: #ffb74d;
        color: white;
        border: none;
    }
    
    .btn-draft:hover {
        background-color: #ff9800;
    }

    /* 施設住所のスタイル */
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
        background-color: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 4px;
        min-height: 20px;
    }
    
    .location-empty {
        color: #999;
        font-style: italic;
    }

    /* モバイル対応 */
    @media (max-width: 768px) {
        .location-selector {
            flex-direction: column;
        }
        
        .location-level {
            width: 100%;
        }
    }

    /* 求人投稿フォームのスタイル */
    .post-job-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .page-title {
        font-size: 24px;
        margin-bottom: 20px;
    }
    
    .success-message {
        background-color: #e8f5e9;
        color: #2e7d32;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .error-message {
        background-color: #ffebee;
        color: #c62828;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .form-section {
        margin-bottom: 30px;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 20px;
        background-color: #fff;
    }
    
    .secti-title {
        font-size: 18px;
        margin-top: 0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .form-row {
        margin-bottom: 20px;
    }
    
    .form-row label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }
    
    .form-row input[type="text"],
    .form-row input[type="url"],
    .form-row textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }
    
    .form-hint {
        display: block;
        font-size: 12px;
        color: #757575;
        margin-top: 5px;
    }
    
    .required {
        color: #f44336;
    }
    
    .taxonomy-select {
        display: flex;
        flex-wrap: wrap;
        margin: -5px;
    }
    
    .checkbox-label {
        display: inline-block;
        margin: 5px;
        padding: 6px 12px;
        background-color: #f5f5f5;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .checkbox-label input {
        margin-right: 5px;
    }
    
    .feature-label {
        background-color: #e3f2fd;
    }
    
    .thumbnail-preview, .voice-image-preview {
        margin-bottom: 10px;
    }
    
    .thumbnail-preview img, .voice-image-preview img {
        max-width: 200px;
        max-height: 200px;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 2px;
    }
    
    .btn-media-upload,
    .btn-media-remove,
    .btn-submit,
    .btn-cancel,
    .btn-view,
    .btn-new,
    .btn-add-item,
    .upload-voice-image,
    .remove-voice-image,
    .remove-schedule-item,
    .remove-voice-item {
        display: inline-block;
        padding: 8px 15px;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        font-size: 14px;
        margin-right: 10px;
        background-color: #f5f5f5;
        color: #333;
        border: 1px solid #ddd;
    }
    
    .btn-media-remove,
    .remove-voice-image,
    .remove-schedule-item,
    .remove-voice-item {
        background-color: #ffebee;
        color: #c62828;
        border: 1px solid #ffcdd2;
    }
    
    .btn-view {
        background-color: #2196f3;
        color: white;
        border: none;
    }
    
    .btn-new, .btn-add-item {
        background-color: #ff9800;
        color: white;
        border: none;
    }
    
    .form-actions {
        margin-top: 20px;
        text-align: center;
    }
    
    .btn-submit {
        background-color: #4caf50;
        color: white;
        border: none;
        font-size: 16px;
        padding: 10px 20px;
    }
    
    .btn-cancel {
        background-color: #f5f5f5;
        color: #333;
        border: 1px solid #ddd;
    }
    
    /* 階層化タクソノミー用スタイル */
    .parent-term {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .parent-label {
        background-color: #e8eaf6;
        font-weight: bold;
    }
    
    .child-label {
        background-color: #f5f5f5;
    }
    
    .grandchild-label {
        background-color: #fafafa;
    }
    
    /* 一日の流れと職員の声のスタイル */
    .daily-schedule-item, .staff-voice-item {
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        margin-bottom: 15px;
		background-color: #fafafa;
        position: relative;
    }
    
    .schedule-time, .schedule-title, .voice-role, .voice-years {
        display: inline-block;
        vertical-align: top;
        margin-right: 15px;
        margin-bottom: 10px;
    }
    
    .schedule-time input, .schedule-title input, .voice-role input, .voice-years input {
        width: 150px;
    }
    
    .schedule-description, .voice-comment, .voice-image {
        margin-bottom: 10px;
    }
    
    .remove-schedule-item, .remove-voice-item {
        position: absolute;
        top: 10px;
        right: 10px;
        padding: 5px 10px;
        font-size: 12px;
    }
    
    /* レスポンシブ対応 */
    @media (max-width: 768px) {
        .post-job-container {
            padding: 10px;
        }
        
        .form-section {
            padding: 15px;
        }
        
        .taxonomy-select {
            flex-direction: column;
        }
        
        .checkbox-label {
            margin: 3px 0;
        }
        
        .schedule-time, .schedule-title, .voice-role, .voice-years {
            display: block;
            margin-right: 0;
        }
    }
    </style>
</div>
<?php 
include(get_stylesheet_directory() . '/agency-footer.php'); 
?>