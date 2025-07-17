<?php
/**
 * Template Name: 加盟教室用の求人編集ページ
 * * 自分が投稿した求人を編集するためのページテンプレート
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

// 現在のユーザー情報を取得
$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// ユーザーが加盟教室（agency）の権限を持っているかチェック
$is_agency = in_array('agency', $current_user->roles);
if (!$is_agency && !current_user_can('administrator')) {
    // 権限がない場合はエラーメッセージ表示
    echo '<div class="error-message">この機能を利用する権限がありません。</div>';
    get_footer();
    exit;
}

// job_idパラメータから編集対象の投稿IDを取得
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;
$job_post = get_post($job_id);

// 投稿が存在しない、または自分の投稿でない（管理者は除く）場合はエラー
if (!$job_post || $job_post->post_type !== 'job' || 
    ($job_post->post_author != $current_user_id && !current_user_can('administrator'))) {
    echo '<div class="error-message">編集する求人情報が見つからないか、編集する権限がありません。</div>';
    get_footer();
    exit;
}

// フォームが送信された場合の処理
if (isset($_POST['update_job']) && isset($_POST['job_nonce']) && 
    wp_verify_nonce($_POST['job_nonce'], 'update_job_' . $job_id)) {
    
    // ★★★ バリデーション追加（ここから） ★★★
    $validation_errors = array();
    
    // 1. サムネイル画像必須チェック
    if (!isset($_POST['thumbnail_ids']) || empty($_POST['thumbnail_ids']) || !is_array($_POST['thumbnail_ids'])) {
        $validation_errors[] = 'サムネイル画像を選択してください。';
    }
    
    // 2. 本文詳細必須チェック
    $job_content_check = isset($_POST['job_content']) ? trim(wp_kses_post($_POST['job_content'])) : '';
    $clean_content_check = strip_tags($job_content_check);
    if (empty($clean_content_check) || $clean_content_check === '' || $job_content_check === '<p></p>' || $job_content_check === '<p><br></p>') {
        $validation_errors[] = '本文詳細を入力してください。';
    }
    
    // 3. 職種必須チェック
    if (!isset($_POST['job_position']) || empty($_POST['job_position']) || !is_array($_POST['job_position'])) {
        $validation_errors[] = '職種を選択してください。';
    } elseif (in_array('other', $_POST['job_position']) && empty(trim($_POST['job_position_other'] ?? ''))) {
        $validation_errors[] = '「その他の職種」を入力してください。';
    }
    
    // 4. 雇用形態必須チェック
    if (!isset($_POST['job_type']) || empty($_POST['job_type']) || !is_array($_POST['job_type'])) {
        $validation_errors[] = '雇用形態を選択してください。';
    } elseif (in_array('others', $_POST['job_type']) && empty(trim($_POST['job_type_other'] ?? ''))) {
        $validation_errors[] = '「その他の雇用形態」を入力してください。';
    }
    
    // 5. 給与形態必須チェック
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
    
    // バリデーションエラーチェック
    if (!empty($validation_errors)) {
        $error = implode('<br>', $validation_errors);
    } else {
        // ★★★ バリデーション通過時のみ更新処理実行（ここから） ★★★
    
    // 基本情報を更新
    $job_data = array(
        'ID' => $job_id,
        'post_title' => sanitize_text_field($_POST['job_title']),
        'post_content' => wp_kses_post($_POST['job_content']),
        'post_status' => 'publish'
    );
    // 施設情報の取得部分に追加 (これは表示用なのでこのままでOK)
    $company_url = get_post_meta($job_id, 'company_url', true);
    // 投稿を更新
    $update_result = wp_update_post($job_data);
    
    if (!is_wp_error($update_result)) {
		// 運営会社のWebサイトURL保存処理を追加 (issetでチェックされているのでこのままでOK)
        if (isset($_POST['company_url'])) {
            update_post_meta($job_id, 'company_url', esc_url_raw($_POST['company_url']));
        }
        // タクソノミーの更新
        // 【修正箇所】勤務地域の更新処理をコメントアウト
        /*
        $job_location_slugs = array();
        if (!empty($_POST['region_value'])) $job_location_slugs[] = sanitize_text_field($_POST['region_value']);
        if (!empty($_POST['prefecture_value'])) $job_location_slugs[] = sanitize_text_field($_POST['prefecture_value']);
        if (!empty($_POST['city_value'])) $job_location_slugs[] = sanitize_text_field($_POST['city_value']);
        
        if (!empty($job_location_slugs)) {
            wp_set_object_terms($job_id, $job_location_slugs, 'job_location');
        } else {
            wp_set_object_terms($job_id, array(), 'job_location');
        }
        */
        
        // 職種（ラジオボタン）の処理
        if (isset($_POST['job_position']) && !empty($_POST['job_position'])) {
            $job_position_value = $_POST['job_position'][0]; // ラジオボタンなので最初の値
            
            if ($job_position_value === 'other' && !empty($_POST['job_position_other'])) {
                // 「その他」が選択され、カスタム入力がある場合
                $custom_job_position = sanitize_text_field($_POST['job_position_other']);
                
                // カスタムフィールドとして保存
                update_post_meta($job_id, 'custom_job_position', $custom_job_position);
                
                // タクソノミーは「その他」のままで保存
                wp_set_object_terms($job_id, 'other', 'job_position');
            } else {
                // 通常のタクソノミー選択の場合
                wp_set_object_terms($job_id, $job_position_value, 'job_position');
                
                // カスタム職種フィールドをクリア（既存データがある場合）
                delete_post_meta($job_id, 'custom_job_position');
            }
        } else {
            wp_set_object_terms($job_id, array(), 'job_position');
            delete_post_meta($job_id, 'custom_job_position');
        }

        // 雇用形態（ラジオボタン）の処理
        if (isset($_POST['job_type']) && !empty($_POST['job_type'])) {
            $job_type_value = $_POST['job_type'][0]; // ラジオボタンなので最初の値
            
            if ($job_type_value === 'others' && !empty($_POST['job_type_other'])) {
                // 「その他」が選択され、カスタム入力がある場合
                $custom_job_type = sanitize_text_field($_POST['job_type_other']);
                
                // カスタムフィールドとして保存
                update_post_meta($job_id, 'custom_job_type', $custom_job_type);
                
                // タクソノミーは「その他」のままで保存
                wp_set_object_terms($job_id, 'others', 'job_type');
            } else {
                // 通常のタクソノミー選択の場合
                wp_set_object_terms($job_id, $job_type_value, 'job_type');
                
                // カスタム雇用形態フィールドをクリア（既存データがある場合）
                delete_post_meta($job_id, 'custom_job_type');
            }
        } else {
            wp_set_object_terms($job_id, array(), 'job_type');
            delete_post_meta($job_id, 'custom_job_type');
        }
        
        // 施設形態を共通情報から自動適用
$common_facility_type = get_user_meta($current_user_id, 'common_facility_type', true);
if (!empty($common_facility_type) && is_array($common_facility_type)) {
    wp_set_object_terms($job_id, $common_facility_type, 'facility_type');
} else {
    wp_set_object_terms($job_id, array(), 'facility_type');
}
        
        // 求人特徴（チェックボックス） (フォームに存在するのでこのまま)
        if (isset($_POST['job_feature'])) {
            wp_set_object_terms($job_id, $_POST['job_feature'], 'job_feature');
        } else {
            wp_set_object_terms($job_id, array(), 'job_feature');
        }
        
        // カスタムフィールドの更新 (フォームに存在するものはこのまま)
        update_post_meta($job_id, 'job_content_title', sanitize_text_field($_POST['job_content_title']));
        update_post_meta($job_id, 'salary_range', sanitize_text_field($_POST['salary_range'])); // 後で給与形態に応じて再計算される
        update_post_meta($job_id, 'working_hours', sanitize_text_field($_POST['working_hours']));
        update_post_meta($job_id, 'holidays', sanitize_text_field($_POST['holidays']));
        update_post_meta($job_id, 'benefits', wp_kses_post($_POST['benefits']));
        update_post_meta($job_id, 'requirements', wp_kses_post($_POST['requirements']));
        update_post_meta($job_id, 'application_process', wp_kses_post($_POST['application_process']));
        update_post_meta($job_id, 'contact_info', wp_kses_post($_POST['contact_info']));
        
        // 【修正箇所】施設情報の更新処理をコメントアウト
        /*
        update_post_meta($job_id, 'facility_name', sanitize_text_field($_POST['facility_name']));
        update_post_meta($job_id, 'facility_tel', sanitize_text_field($_POST['facility_tel']));
        update_post_meta($job_id, 'facility_hours', sanitize_text_field($_POST['facility_hours']));
        update_post_meta($job_id, 'facility_url', esc_url_raw($_POST['facility_url']));
        update_post_meta($job_id, 'facility_company', sanitize_text_field($_POST['facility_company']));
        update_post_meta($job_id, 'facility_map', wp_kses($_POST['facility_map'], array(
            'iframe' => array(
                'src' => array(),
                'width' => array(),
                'height' => array(),
                'frameborder' => array(),
                'style' => array(),
                'allowfullscreen' => array()
            )
        )));
        */
        
        // 【修正箇所】郵便番号と詳細住所の更新処理をコメントアウト
        /*
        update_post_meta($job_id, 'facility_zipcode', sanitize_text_field($_POST['facility_zipcode']));
        update_post_meta($job_id, 'facility_address_detail', sanitize_text_field($_POST['facility_address_detail']));
        */

        // 【修正箇所】完全な住所の組み立てと保存処理をコメントアウト (上記に依存するため)
        /*
        $location_terms = wp_get_object_terms($job_id, 'job_location', array('fields' => 'all'));
        $prefecture = '';
        $city = '';

        foreach ($location_terms as $term) {
            $ancestors = get_ancestors($term->term_id, 'job_location', 'taxonomy');
            if (count($ancestors) == 2) {
                $city = $term->name;
                $prefecture_term = get_term($ancestors[0], 'job_location');
                if ($prefecture_term && !is_wp_error($prefecture_term)) {
                    $prefecture = $prefecture_term->name;
                }
                break;
            } else if (count($ancestors) == 1 && empty($prefecture)) {
                $prefecture = $term->name;
            }
        }

        $full_address = '〒' . $_POST['facility_zipcode'] . ' ' . $prefecture . $city . $_POST['facility_address_detail'];
        update_post_meta($job_id, 'facility_address', $full_address);
        */
        
        // 追加フィールドの更新 (フォームに存在するものはこのまま)
        update_post_meta($job_id, 'bonus_raise', wp_kses_post($_POST['bonus_raise']));
        // 【修正箇所】capacity と staff_composition の更新処理をコメントアウト
        // update_post_meta($job_id, 'capacity', sanitize_text_field($_POST['capacity']));
        // update_post_meta($job_id, 'staff_composition', wp_kses_post($_POST['staff_composition']));
        
        // 給与情報の更新 (フォームに存在するのでこのまま)
        update_post_meta($job_id, 'salary_type', sanitize_text_field($_POST['salary_type']));
        update_post_meta($job_id, 'salary_form', sanitize_text_field($_POST['salary_form']));
        update_post_meta($job_id, 'salary_min', sanitize_text_field($_POST['salary_min']));
        update_post_meta($job_id, 'salary_max', sanitize_text_field($_POST['salary_max']));
        update_post_meta($job_id, 'fixed_salary', sanitize_text_field($_POST['fixed_salary']));
        update_post_meta($job_id, 'salary_remarks', wp_kses_post($_POST['salary_remarks']));

        // 旧形式との互換性のため、salary_rangeも更新
        if ($_POST['salary_form'] === 'fixed') {
            $salary_range_recalculated = sanitize_text_field($_POST['fixed_salary']);
        } else {
            $salary_range_recalculated = sanitize_text_field($_POST['salary_min']) . '〜' . sanitize_text_field($_POST['salary_max']);
        }
        update_post_meta($job_id, 'salary_range', $salary_range_recalculated); // 再計算した値で更新
        
       // サムネイル画像の処理
        if (isset($_POST['thumbnail_ids']) && is_array($_POST['thumbnail_ids'])) {
            $thumbnail_ids = array_map('intval', $_POST['thumbnail_ids']);
            
            // 複数画像IDをメタデータとして保存
            update_post_meta($job_id, 'job_thumbnail_ids', $thumbnail_ids);
            
            // 最初の画像をメインのサムネイルに設定
            if (!empty($thumbnail_ids)) {
                set_post_thumbnail($job_id, $thumbnail_ids[0]);
            } else {
                // 画像がなければサムネイルを削除
                delete_post_thumbnail($job_id);
            }
        } else {
            // 画像選択がない場合はメタデータとサムネイルを削除
            delete_post_meta($job_id, 'job_thumbnail_ids');
            delete_post_thumbnail($job_id);
        }
        
        // 仕事の一日の流れ（配列形式）
        if (isset($_POST['daily_schedule_time']) && is_array($_POST['daily_schedule_time'])) {
            $schedule_items = array();
            $count = count($_POST['daily_schedule_time']);
            
            for ($i = 0; $i < $count; $i++) {
                if (!empty($_POST['daily_schedule_time'][$i])) { // 時間が入力されている項目のみ保存
                    $schedule_items[] = array(
                        'time' => sanitize_text_field($_POST['daily_schedule_time'][$i]),
                        'title' => sanitize_text_field($_POST['daily_schedule_title'][$i]),
                        'description' => wp_kses_post($_POST['daily_schedule_description'][$i])
                    );
                }
            }
            
            update_post_meta($job_id, 'daily_schedule_items', $schedule_items);
        }
        
        // 職員の声（配列形式）
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
            
            update_post_meta($job_id, 'staff_voice_items', $voice_items);
        }
        
        // 成功メッセージ表示と求人詳細ページへのリンク
        $success = true;
    } else {
        // エラーメッセージ表示
        $error = $update_result->get_error_message();
        if (empty($error)) {
            $error = '不明なエラーが発生しました。再度お試しください。';
        }
    }
}
}
// 投稿データを再取得
$job_post = get_post($job_id); // 再取得
$job_title = $job_post->post_title;
$job_content = $job_post->post_content;

// タクソノミー情報を再取得
$current_job_location = wp_get_object_terms($job_id, 'job_location', array('fields' => 'slugs'));
$current_job_position = wp_get_object_terms($job_id, 'job_position', array('fields' => 'slugs'));
$current_job_type = wp_get_object_terms($job_id, 'job_type', array('fields' => 'slugs'));
$current_facility_type = wp_get_object_terms($job_id, 'facility_type', array('fields' => 'slugs'));
$current_job_feature = wp_get_object_terms($job_id, 'job_feature', array('fields' => 'slugs'));

// カスタムフィールドから「その他」の値を再取得
$custom_job_position = get_post_meta($job_id, 'custom_job_position', true);
$custom_job_type = get_post_meta($job_id, 'custom_job_type', true);

// カスタムフィールドデータを再取得
$job_content_title = get_post_meta($job_id, 'job_content_title', true);
$salary_range = get_post_meta($job_id, 'salary_range', true);
$working_hours = get_post_meta($job_id, 'working_hours', true);
$holidays = get_post_meta($job_id, 'holidays', true);
$benefits = get_post_meta($job_id, 'benefits', true);
$requirements = get_post_meta($job_id, 'requirements', true);
$application_process = get_post_meta($job_id, 'application_process', true);
$contact_info = get_post_meta($job_id, 'contact_info', true);
$bonus_raise = get_post_meta($job_id, 'bonus_raise', true);

// 給与情報の再取得
$salary_type = get_post_meta($job_id, 'salary_type', true);
$salary_form = get_post_meta($job_id, 'salary_form', true);
$salary_min = get_post_meta($job_id, 'salary_min', true);
$salary_max = get_post_meta($job_id, 'salary_max', true);
$fixed_salary = get_post_meta($job_id, 'fixed_salary', true);
$salary_remarks = get_post_meta($job_id, 'salary_remarks', true);

// その他必要なデータの再取得
$daily_schedule_items = get_post_meta($job_id, 'daily_schedule_items', true);
$staff_voice_items = get_post_meta($job_id, 'staff_voice_items', true);

// 施設情報を再取得
$facility_name = get_post_meta($job_id, 'facility_name', true);
$facility_address = get_post_meta($job_id, 'facility_address', true);
$facility_tel = get_post_meta($job_id, 'facility_tel', true);
$facility_hours = get_post_meta($job_id, 'facility_hours', true);
$facility_url = get_post_meta($job_id, 'facility_url', true);
$facility_company = get_post_meta($job_id, 'facility_company', true);
$facility_map = get_post_meta($job_id, 'facility_map', true);
$company_url = get_post_meta($job_id, 'company_url', true);
$capacity = get_post_meta($job_id, 'capacity', true);
$staff_composition = get_post_meta($job_id, 'staff_composition', true);

// 住所関連の情報を再取得
$facility_zipcode = get_post_meta($job_id, 'facility_zipcode', true);
$facility_address_detail = get_post_meta($job_id, 'facility_address_detail', true);

?>
								   



<div class="edit-job-container">
    <h1 class="page-title">求人情報の編集</h1>
    
    <?php if (isset($success) && $success): ?>
    <div class="success-message">
        <p>求人情報を更新しました。</p>
        <p>
            <a href="<?php echo get_permalink($job_id); ?>" class="btn-view">更新した求人を確認する</a>
            <?php
            // 下書きにするボタンを追加（nonceを含む）
            $draft_url = admin_url('admin-post.php?action=draft_job&job_id=' . $job_id . '&_wpnonce=' . wp_create_nonce('draft_job_' . $job_id));
            ?>
            <a href="<?php echo $draft_url; ?>" class="btn-draft">下書きにする</a>
        </p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($error) && !empty($error)): ?>
    <div class="error-message">
        <p>エラーが発生しました: <?php echo esc_html($error); // エラーメッセージもエスケープ ?></p>
    </div>
    <?php endif; ?>
    
    <form method="post" class="edit-job-form" enctype="multipart/form-data">
        <?php wp_nonce_field('update_job_' . $job_id, 'job_nonce'); ?>
        
        <div class="form-section">
            <h2 class="secti-title">基本情報</h2>
            
            <div class="form-row">
                <label for="job_title">求人タイトル <span class="required">*</span></label>
                <input type="text" id="job_title" name="job_title" value="<?php echo esc_attr($job_title); ?>" required><span class="form-hint">【教室名】＋【募集職種】＋【雇用形態】＋【特徴】を書くことをお勧めします。<br>例：こどもプラス○○駅前教室　放課後等デイサービス指導員　パート／アルバイト　週3日～OK</span>
            </div>
            
            <div class="form-row">
                <label>サムネイル画像 <span class="required">*</span></label>
                <div id="thumbnails-container">
                    <?php 
                    // 現在の画像IDリストを取得
                    $thumbnail_ids = get_post_meta($job_id, 'job_thumbnail_ids', true);
                    if (empty($thumbnail_ids) || !is_array($thumbnail_ids)) { // 配列でない場合も考慮
                        $thumbnail_ids = array();
                        // 従来のサムネイルIDがある場合は追加
                        $old_thumbnail_id = get_post_thumbnail_id($job_id);
                        if ($old_thumbnail_id) {
                            $thumbnail_ids[] = $old_thumbnail_id;
                        }
                    }
                    
                    // 画像プレビューの表示
                    if (!empty($thumbnail_ids)) {
                        foreach ($thumbnail_ids as $thumb_id) {
                            if ($image_url = wp_get_attachment_url($thumb_id)) {
                                echo '<div class="thumbnail-item">';
                                echo '<div class="thumbnail-preview"><img src="' . esc_url($image_url) . '" alt="サムネイル画像"></div>';
                                echo '<input type="hidden" name="thumbnail_ids[]" value="' . esc_attr($thumb_id) . '">';
                                echo '<button type="button" class="remove-thumbnail-btn">削除</button>';
                                echo '</div>';
                            }
                        }
                    }
                    ?>
                </div>
                <button type="button" class="btn-media-upload" id="upload_thumbnails">画像を追加</button>
                <p class="form-hint">スライドで複数画像が掲載可能です。画像の順番はドラッグ&ドロップで変更できます。</p>
            </div>
            
            <div class="form-row">
                <label for="job_content_title">本文タイトル <span class="required">*</span><span class="required">※系列教室などで使いまわしＮＧ！かぶらないようにしてください。</span></label>
                <input type="text" id="job_content_title" name="job_content_title" value="<?php echo esc_attr($job_content_title); ?>" required><span class="form-hint">何を説明するか一目でわかる短文に。全角15文字程度が目安。<br>例：週休2日制・残業ほぼなし！児童発達支援管理責任者として活躍しませんか？</span>
            </div>
            
            <div class="form-row">
                <label for="job_content">本文詳細 <span class="required">*</span><span class="required">※系列教室などで使いまわしＮＧ！かぶらないようにしてください。</span></label>
                <?php 
                wp_editor($job_content, 'job_content', array(
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
                    // 現在選択されている地域情報を表示
                    $location_terms_display = wp_get_object_terms($job_id, 'job_location', array('fields' => 'all'));
                    $region_name = '';
                    $prefecture_name = '';
                    $city_name = '';
                    
                    if (!empty($location_terms_display)) {
                        // 親から辿って表示順を制御する
                        $term_hierarchy = array();
                        foreach ($location_terms_display as $term) {
                            if ($term->parent == 0) {
                                $region_name = $term->name;
                            } else {
                                $parent_term = get_term($term->parent, 'job_location');
                                if ($parent_term && $parent_term->parent == 0) {
                                    $prefecture_name = $term->name;
                                } else {
                                    $city_name = $term->name; // 孫と仮定
                                }
                            }
                        }
                    }
                    
                    $location_display_parts = [];
                    if (!empty($region_name)) $location_display_parts[] = $region_name;
                    if (!empty($prefecture_name)) $location_display_parts[] = $prefecture_name;
                    if (!empty($city_name)) $location_display_parts[] = $city_name;

                    $location_display = implode(' > ', $location_display_parts);
                    
                    if (empty($location_display)) {
                        $location_display = '設定されていません';
                    }
                    
                    echo '<p class="readonly-value">' . esc_html($location_display) . '</p>';
                    ?>
                    <p class="form-hint">※ 勤務地域の変更は「求人情報管理」ページの共通情報編集で行えます。</p>
                </div>
            </div>
            
            <div class="form-row">
                <label>職種 <span class="required">*</span></label>
                <div class="taxonomy-select">
                    <?php 
                    $job_position_terms = get_terms(array(
                        'taxonomy' => 'job_position',
                        'hide_empty' => false,
                    ));
                    
                    if ($job_position_terms && !is_wp_error($job_position_terms)) {
                        foreach ($job_position_terms as $term) {
                            $checked = '';
                            if (isset($current_job_position) && in_array($term->slug, $current_job_position)) {
                                $checked = 'checked';
                            }
                            echo '<label class="radio-label">';
                            echo '<input type="radio" name="job_position[]" value="' . esc_attr($term->slug) . '" ' . $checked . ' required>'; // required追加
                            echo esc_html($term->name);
                            echo '</label>';
                        }
                    }
                    ?>
                </div>
                
                <div id="job-position-other-field" class="other-input-field" style="<?php echo (isset($current_job_position) && in_array('other', $current_job_position) && !empty($custom_job_position)) ? 'display: block;' : 'display: none;'; ?>">
                    <label for="job_position_other">その他の職種を入力 <span class="required">*</span></label>
                    <input type="text" id="job_position_other" name="job_position_other" value="<?php echo esc_attr($custom_job_position); ?>" placeholder="具体的な職種名を入力してください">
                </div>
            </div>
            
            <div class="form-row">
                <label>雇用形態 <span class="required">*</span></label>
                <div class="taxonomy-select">
                    <?php 
                    $job_type_terms = get_terms(array(
                        'taxonomy' => 'job_type',
                        'hide_empty' => false,
                    ));
                    
                    if ($job_type_terms && !is_wp_error($job_type_terms)) {
                        foreach ($job_type_terms as $term) {
                            $checked = '';
                            if (isset($current_job_type) && in_array($term->slug, $current_job_type)) {
                                $checked = 'checked';
                            }
                            echo '<label class="radio-label">';
                            echo '<input type="radio" name="job_type[]" value="' . esc_attr($term->slug) . '" ' . $checked . ' required>'; // required追加
                            echo esc_html($term->name);
                            echo '</label>';
                        }
                    }
                    ?>
                </div>
                
                <div id="job-type-other-field" class="other-input-field" style="<?php echo (isset($current_job_type) && in_array('others', $current_job_type) && !empty($custom_job_type)) ? 'display: block;' : 'display: none;'; ?>">
                    <label for="job_type_other">その他の雇用形態を入力 <span class="required">*</span></label>
                    <input type="text" id="job_type_other" name="job_type_other" value="<?php echo esc_attr($custom_job_type); ?>" placeholder="具体的な雇用形態を入力してください">
                </div>
            </div>
            
           <div class="form-row">
                <label for="contact_info">仕事内容 <span class="required">*</span></label>
                <textarea id="contact_info" name="contact_info" rows="5" required><?php echo esc_textarea($contact_info); ?></textarea>
                <span class="form-hint">具体的な業務内容や仕事の特徴など</span>
            </div>

            <div class="form-row">
                <label for="requirements">応募要件 <span class="required">*</span></label>
                <textarea id="requirements" name="requirements" rows="5" required><?php echo esc_textarea($requirements); ?></textarea>
                <span class="form-hint">必要な資格や経験など</span>
            </div>
            
            <div class="form-row">
                <label for="working_hours">勤務時間 <span class="required">*</span></label>
                <textarea id="working_hours" name="working_hours" rows="3" required><?php echo esc_textarea($working_hours); ?></textarea>
                <span class="form-hint">例: 9:00〜18:00（休憩60分）</span>
            </div>
            
            <div class="form-row">
                <label for="holidays">休日・休暇 <span class="required">*</span></label>
                <textarea id="holidays" name="holidays" rows="3" required><?php echo esc_textarea($holidays); ?></textarea>
                <span class="form-hint">例: 土日祝、年末年始、有給休暇あり</span>
            </div>
            
            <div class="form-row">
                <label for="benefits">福利厚生 <span class="required">*</span></label>
                <textarea id="benefits" name="benefits" rows="5" required><?php echo esc_textarea($benefits); ?></textarea>
                <span class="form-hint">社会保険、交通費支給、各種手当など</span>
            </div>
            
            <div class="form-row">
                <label for="salary_type">賃金形態 <span class="required">*</span></label>
                <select id="salary_type" name="salary_type" required>
                    <option value="MONTH" <?php selected(get_post_meta($job_id, 'salary_type', true), 'MONTH'); ?>>月給</option>
                    <option value="HOUR" <?php selected(get_post_meta($job_id, 'salary_type', true), 'HOUR'); ?>>時給</option>
                </select>
            </div>
            
            <div class="form-row">
                <label>給与形態 <span class="required">*</span></label>
                <div class="radio-wrapper">
                    <label>
                        <input type="radio" name="salary_form" value="fixed" <?php checked(get_post_meta($job_id, 'salary_form', true), 'fixed'); ?> required> 
                        給与に幅がない（固定給）
                    </label>
                    <label>
                        <input type="radio" name="salary_form" value="range" <?php checked(get_post_meta($job_id, 'salary_form', true), 'range'); ?> required> 
                        給与に幅がある（範囲給）
                    </label>
                </div>
            </div>
            
            <div id="fixed-salary-field" class="form-row salary-field" style="display: none;">
                <label for="fixed_salary">給与（固定給） <span class="required">*</span><span class="required">※半角数字のみカンマ「,」や「円」は無し</span></label>
                <input type="text" id="fixed_salary" name="fixed_salary" value="<?php echo esc_attr(get_post_meta($job_id, 'fixed_salary', true)); ?>">
                <span class="form-hint">例: 250000</span>
            </div>
            
            <div id="range-salary-fields" class="salary-field" style="display: none;">
                <div class="form-row">
                    <label for="salary_min">給与①最低賃金 <span class="required">*</span><span class="required">※半角数字のみカンマ「,」や「円」は無し</span></label>
                    <input type="text" id="salary_min" name="salary_min" value="<?php echo esc_attr(get_post_meta($job_id, 'salary_min', true)); ?>">
                    <span class="form-hint">例: 200000</span>
                </div>
                
                <div class="form-row">
                    <label for="salary_max">給与②最高賃金 <span class="required">*</span><span class="required">※半角数字のみカンマ「,」や「円」は無し</span></label>
                    <input type="text" id="salary_max" name="salary_max" value="<?php echo esc_attr(get_post_meta($job_id, 'salary_max', true)); ?>">
                    <span class="form-hint">例: 300000</span>
                </div>
            </div>
            
            <div class="form-row">
                <label for="salary_remarks">給料についての備考</label>
                <textarea id="salary_remarks" name="salary_remarks" rows="3"><?php echo esc_textarea(get_post_meta($job_id, 'salary_remarks', true)); ?></textarea>
                <span class="form-hint">例: 経験・能力により優遇。試用期間3ヶ月あり（同条件）。</span>
            </div>
            
            <input type="hidden" id="salary_range" name="salary_range" value="<?php echo esc_attr($salary_range); ?>">

            <div class="form-row">
                <label for="bonus_raise">昇給・賞与</label>
                <textarea id="bonus_raise" name="bonus_raise" rows="5"><?php echo esc_textarea($bonus_raise); ?></textarea>
                <span class="form-hint">昇給制度や賞与の詳細など</span>
            </div>
            
            <div class="form-row">
                <label for="application_process">選考プロセス</label>
                <textarea id="application_process" name="application_process" rows="5"><?php echo esc_textarea($application_process); ?></textarea>
                <span class="form-hint">書類選考、面接回数など</span>
            </div>
        </div>
        
        <div class="form-section">
            <h2 class="secti-title">求人の特徴</h2>
            
            <?php 
            $parent_feature_terms = get_terms(array(
                'taxonomy' => 'job_feature',
                'hide_empty' => false,
                'parent' => 0
            ));
            
            if ($parent_feature_terms && !is_wp_error($parent_feature_terms)) {
                echo '<div class="feature-accordion-container">';
                foreach ($parent_feature_terms as $parent_term) {
                    echo '<div class="feature-accordion">';
                    echo '<div class="feature-accordion-header">';
                    echo '<h3>' . esc_html($parent_term->name) . '</h3>';
                    echo '<span class="accordion-icon">+</span>';
                    echo '</div>';
                    
                    $child_feature_terms = get_terms(array(
                        'taxonomy' => 'job_feature',
                        'hide_empty' => false,
                        'parent' => $parent_term->term_id
                    ));
                    
                    if ($child_feature_terms && !is_wp_error($child_feature_terms)) {
                        echo '<div class="feature-accordion-content" style="display: none;">'; // 初期非表示
                        echo '<div class="taxonomy-select">';
                        foreach ($child_feature_terms as $term) {
                            $checked = (isset($current_job_feature) && in_array($term->slug, $current_job_feature)) ? 'checked' : '';
                            echo '<label class="checkbox-label feature-label">';
                            echo '<input type="checkbox" name="job_feature[]" value="' . esc_attr($term->slug) . '" ' . $checked . '>';
                            echo esc_html($term->name);
                            echo '</label>';
                        }
                        echo '</div>';
                        echo '</div>';
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
                    if (is_array($daily_schedule_items) && !empty($daily_schedule_items)) {
                        foreach ($daily_schedule_items as $index => $item) {
                            ?>
                            <div class="daily-schedule-item">
                                <div class="schedule-time">
                                    <label>時間</label>
                                    <input type="text" name="daily_schedule_time[]" value="<?php echo esc_attr($item['time']); ?>" placeholder="9:00">
                                </div>
                                <div class="schedule-title">
                                    <label>タイトル</label>
                                    <input type="text" name="daily_schedule_title[]" value="<?php echo esc_attr($item['title']); ?>" placeholder="出社・朝礼">
                                </div>
                                <div class="schedule-description">
                                    <label>詳細</label>
                                    <textarea name="daily_schedule_description[]" rows="3" placeholder="出社して業務の準備をします。朝礼で1日の予定を確認します。"><?php echo esc_textarea($item['description']); ?></textarea>
                                </div>
                                <?php if ($index > 0): ?>
                                <button type="button" class="remove-schedule-item">削除</button>
                                <?php else: ?>
                                <button type="button" class="remove-schedule-item" style="display:none;">削除</button>
                                <?php endif; ?>
                            </div>
                            <?php
                        }
                    } else {
                        ?>
                        <div class="daily-schedule-item">
                            <div class="schedule-time">
                                <label>時間</label>
                                <input type="text" name="daily_schedule_time[]" placeholder="9:00">
                            </div>
                            <div class="schedule-title">
                                <label>タイトル</label>
                                <input type="text" name="daily_schedule_title[]" placeholder="出社・朝礼">
                            </div>
                            <div class="schedule-description">
                                <label>詳細</label>
                                <textarea name="daily_schedule_description[]" rows="3" placeholder="出社して業務の準備をします。朝礼で1日の予定を確認します。"></textarea>
                            </div>
                            <button type="button" class="remove-schedule-item" style="display:none;">削除</button>
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <button type="button" id="add-schedule-item" class="btn-add-item">時間枠を追加</button>
            </div>
            
            
        </div>
        
        <div class="form-section">
            <h2 class="secti-title">事業所の情報</h2>
            <p class="section-note">※ 事業所情報の変更は「求人情報管理」ページの共通情報編集で行えます。</p>
            
            <div class="readonly-info-grid">
                <div class="readonly-item">
                    <label>施設名</label>
                    <div class="readonly-value"><?php echo esc_html($facility_name ?: '未設定'); ?></div>
                </div>
                
                <div class="readonly-item">
                    <label>運営会社名</label>
                    <div class="readonly-value"><?php echo esc_html($facility_company ?: '未設定'); ?></div>
                </div>
                
                <div class="readonly-item">
                    <label>運営会社のWebサイトURL</label>
                    <div class="readonly-value">
                        <?php if ($company_url): ?>
                            <a href="<?php echo esc_url($company_url); ?>" target="_blank"><?php echo esc_html($company_url); ?></a>
                        <?php else: ?>
                            未設定
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="readonly-item">
                    <label>施設住所</label>
                    <div class="readonly-value"><?php echo esc_html($facility_address ?: '未設定'); ?></div>
                </div>
                
                <div class="readonly-item">
                    <label>GoogleMap</label>
                    <div class="readonly-value">
                        <?php if ($facility_map): ?>
                            <div class="map-preview"><?php echo $facility_map; // iframeなのでwp_kses_postなどは使わない前提、保存時に処理済み ?></div>
                        <?php else: ?>
                            未設定
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="readonly-item">
                    <label>施設形態</label>
                    <div class="readonly-value">
                        <?php
                        // 施設形態はフォームで編集可能なので、最新の値を表示するために $current_facility_type を使用
                        $facility_type_terms_display = wp_get_object_terms($job_id, 'facility_type');
                        if (!empty($facility_type_terms_display) && !is_wp_error($facility_type_terms_display)) {
                            $facility_type_names = array();
                            foreach ($facility_type_terms_display as $term) {
                                $facility_type_names[] = $term->name;
                            }
                            echo esc_html(implode(', ', $facility_type_names));
                        } else {
                            echo '未設定';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="readonly-item">
                    <label>利用者定員数</label>
                    <div class="readonly-value"><?php echo esc_html($capacity ?: '未設定'); ?></div>
                </div>
                
                <div class="readonly-item">
                    <label>スタッフ構成</label>
                    <div class="readonly-value"><?php echo wp_kses_post($staff_composition ?: '未設定'); // 保存時にksesしているので表示時も合わせる ?></div>
                </div>
                
                <div class="readonly-item">
                    <label>施設電話番号</label>
                    <div class="readonly-value"><?php echo esc_html($facility_tel ?: '未設定'); ?></div>
                </div>
                
                <div class="readonly-item">
                    <label>施設営業時間</label>
                    <div class="readonly-value"><?php echo esc_html($facility_hours ?: '未設定'); ?></div>
                </div>
                
                <div class="readonly-item">
                    <label>施設WebサイトURL</label>
                    <div class="readonly-value">
                        <?php if ($facility_url): ?>
                            <a href="<?php echo esc_url($facility_url); ?>" target="_blank"><?php echo esc_html($facility_url); ?></a>
                        <?php else: ?>
                            未設定
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        

<div class="form-actions">
    <input type="submit" name="update_job" value="求人情報を更新する" class="btn-submit">
    <a href="<?php echo esc_url(home_url('/job-list/')); ?>" class="btn-cancel">キャンセル</a>
</div>


    </form>
    
    <script>
    jQuery(document).ready(function($) {
        // 複数サムネイル画像用のメディアアップローダー
        $('#upload_thumbnails').click(function(e) {
            e.preventDefault();
            
            var custom_uploader = wp.media({
                title: '求人サムネイル画像を選択',
                button: {
                    text: '画像を選択'
                },
                multiple: true
            });
            
            custom_uploader.on('select', function() {
                var attachments = custom_uploader.state().get('selection').toJSON();
                
                $.each(attachments, function(index, attachment) {
                    var $thumbnailItem = $('<div class="thumbnail-item"></div>');
                    $thumbnailItem.append('<div class="thumbnail-preview"><img src="' + attachment.url + '" alt="サムネイル画像"></div>');
                    $thumbnailItem.append('<input type="hidden" name="thumbnail_ids[]" value="' + attachment.id + '">');
                    $thumbnailItem.append('<button type="button" class="remove-thumbnail-btn">削除</button>');
                    $('#thumbnails-container').append($thumbnailItem);
                });
            });
            
            custom_uploader.open();
        });

        $(document).on('click', '.remove-thumbnail-btn', function() {
            $(this).closest('.thumbnail-item').remove();
        });

        if ($.fn.sortable) {
            $('#thumbnails-container').sortable({
                placeholder: 'ui-state-highlight'
            });
            $('#thumbnails-container').disableSelection();
        }
            
        $('input[name="salary_form"]').on('change', function() {
            $('.salary-field').hide();
            $('#fixed_salary, #salary_min, #salary_max').prop('required', false); // いったん全てfalseに
            
            if ($(this).val() === 'fixed') {
                $('#fixed-salary-field').show();
                $('#fixed_salary').prop('required', true);
            } else if ($(this).val() === 'range') { // valueがrangeの場合も明示的に
                $('#range-salary-fields').show();
                $('#salary_min, #salary_max').prop('required', true);
            }
        });
        
        // 初期状態でどちらもチェックされていない場合、範囲給をデフォルトでチェックしトリガー
        if (!$('input[name="salary_form"]:checked').length) {
             $('input[name="salary_form"][value="range"]').prop('checked', true);
        }
        $('input[name="salary_form"]:checked').trigger('change'); // 初期表示のためトリガー
        
        $('input[name="job_position[]"]').on('change', function() {
            var selectedValue = $(this).val();
            var $otherField = $('#job-position-other-field');
            var $otherInput = $('#job_position_other');
            
            if (selectedValue === 'other' && $(this).is(':checked')) {
                $otherField.show();
                $otherInput.prop('required', true);
            } else {
                $otherField.hide();
                $otherInput.prop('required', false).val('');
            }
        });
        
        $('input[name="job_type[]"]').on('change', function() {
            var selectedValue = $(this).val();
            var $otherField = $('#job-type-other-field');
            var $otherInput = $('#job_type_other');
            
            if (selectedValue === 'others' && $(this).is(':checked')) {
                $otherField.show();
                $otherInput.prop('required', true);
            } else {
                $otherField.hide();
                $otherInput.prop('required', false).val('');
            }
        });
        
        var $checkedJobPosition = $('input[name="job_position[]"]:checked');
        if ($checkedJobPosition.length && $checkedJobPosition.val() === 'other') {
            $('#job-position-other-field').show();
            $('#job_position_other').prop('required', true);
        } else {
             $('#job-position-other-field').hide();
            $('#job_position_other').prop('required', false);
        }
        
        var $checkedJobType = $('input[name="job_type[]"]:checked');
        if ($checkedJobType.length && $checkedJobType.val() === 'others') {
            $('#job-type-other-field').show();
            $('#job_type_other').prop('required', true);
        } else {
            $('#job-type-other-field').hide();
            $('#job_type_other').prop('required', false);
        }
        
        $('.feature-accordion-header').on('click', function() {
            var $accordion = $(this).parent();
            var $content = $accordion.find('.feature-accordion-content');
            var $icon = $(this).find('.accordion-icon');
            
            $content.slideToggle(function() { // slideToggleで見えているかで判定
                if ($content.is(':visible')) {
                    $icon.text('-');
                } else {
                    $icon.text('+');
                }
            });
        });
        
        $('.feature-accordion').each(function() {
            var $accordion = $(this);
            var $content = $accordion.find('.feature-accordion-content');
            var $icon = $accordion.find('.accordion-icon');
            if ($accordion.find('input:checked').length > 0) {
                $content.show();
                $icon.text('-');
            } else {
                $content.hide(); // チェックがなければ隠す
                $icon.text('+');
            }
        });
        
        $('#add-schedule-item').on('click', function() {
            var $container = $('#daily-schedule-container');
            var $newItem = $container.find('.daily-schedule-item:first').clone(true); // イベントハンドラもコピー
            $newItem.find('input, textarea').val('');
            $newItem.find('.remove-schedule-item').show(); // 新規追加分は削除ボタン表示
            $container.append($newItem);
        });
        
        $(document).on('click', '.remove-schedule-item', function() {
             // 最低1つは残す (ただし、最初の要素の削除ボタンは非表示なので、実質2つ以上ある場合のみ)
            if ($('#daily-schedule-container .daily-schedule-item').length > 1) {
                $(this).closest('.daily-schedule-item').remove();
            } else {
                // 最後の1つの場合は値をクリアする（任意）
                $(this).closest('.daily-schedule-item').find('input, textarea').val('');
            }
        });
        
        $('#add-voice-item').on('click', function() {
            var $container = $('#staff-voice-container');
            var $newItem = $container.find('.staff-voice-item:first').clone(true); // イベントハンドラもコピー
            $newItem.find('input, textarea').val('');
            $newItem.find('.voice-image-preview').empty();
            $newItem.find('input[name^="staff_voice_image"]').val('');
            $newItem.find('.remove-voice-image').hide(); // 新しいアイテムの画像削除は最初は非表示
            $newItem.find('.remove-voice-item').show(); // 新規追加分は削除ボタン表示
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
        
        $(document).on('click', '.remove-voice-image', function() {
            var $item = $(this).closest('.staff-voice-item');
            $item.find('.voice-image-preview').empty();
            $item.find('input[name^="staff_voice_image"]').val('');
            $(this).hide();
        });

       $('.edit-job-form').on('submit', function(e) {
    let formIsValid = true;
    let errorMessages = [];

    // 1. サムネイル画像の必須チェック
    var thumbnailCount = $('input[name="thumbnail_ids[]"]').length;
    console.log('サムネイル数:', thumbnailCount); // デバッグ用
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
    console.log('エディタ内容:', editorContent); // デバッグ用
    if (!cleanContent || cleanContent === '') {
        errorMessages.push('本文詳細を入力してください。');
        formIsValid = false;
    }

    // 3. 職種の必須チェック
    if ($('input[name="job_position[]"]:checked').length === 0) {
        errorMessages.push('職種を選択してください。');
        formIsValid = false;
    } else if ($('input[name="job_position[]"][value="other"]:checked').length > 0 && $('#job_position_other').val().trim() === '') {
        errorMessages.push('「その他の職種」を入力してください。');
        formIsValid = false;
    }

    // 4. 雇用形態の必須チェック
    if ($('input[name="job_type[]"]:checked').length === 0) {
        errorMessages.push('雇用形態を選択してください。');
        formIsValid = false;
    } else if ($('input[name="job_type[]"][value="others"]:checked').length > 0 && $('#job_type_other').val().trim() === '') {
        errorMessages.push('「その他の雇用形態」を入力してください。');
        formIsValid = false;
    }
    
    // 5. 給与形態の必須チェック
    if ($('input[name="salary_form"]:checked').length === 0) {
        errorMessages.push('給与形態を選択してください。');
        formIsValid = false;
    } else {
        if ($('input[name="salary_form"][value="fixed"]:checked').length > 0 && $('#fixed_salary').val().trim() === '') {
            errorMessages.push('給与（固定給）を入力してください。');
            formIsValid = false;
        }
        if ($('input[name="salary_form"][value="range"]:checked').length > 0) {
            if ($('#salary_min').val().trim() === '') {
                errorMessages.push('給与①最低賃金を入力してください。');
                formIsValid = false;
            }
            if ($('#salary_max').val().trim() === '') {
                errorMessages.push('給与②最高賃金を入力してください。');
                formIsValid = false;
            }
        }
    }

            // 職種の必須チェック
            if ($('input[name="job_position[]"]:checked').length === 0) {
                alert('職種を選択してください。');
                formIsValid = false;
                // エラー箇所にスクロールするなどのUX向上も検討可
            } else if ($('input[name="job_position[]"][value="other"]:checked').length > 0 && $('#job_position_other').val().trim() === '') {
                alert('「その他の職種」を入力してください。');
                $('#job_position_other').focus();
                formIsValid = false;
            }

            // 雇用形態の必須チェック
            if ($('input[name="job_type[]"]:checked').length === 0) {
                alert('雇用形態を選択してください。');
                formIsValid = false;
            } else if ($('input[name="job_type[]"][value="others"]:checked').length > 0 && $('#job_type_other').val().trim() === '') {
                alert('「その他の雇用形態」を入力してください。');
                $('#job_type_other').focus();
                formIsValid = false;
            }
            
            // 給与形態の必須チェック
            if ($('input[name="salary_form"]:checked').length === 0) {
                alert('給与形態を選択してください。');
                formIsValid = false;
            } else {
                if ($('input[name="salary_form"][value="fixed"]:checked').length > 0 && $('#fixed_salary').val().trim() === '') {
                     alert('給与（固定給）を入力してください。');
                     $('#fixed_salary').focus();
                     formIsValid = false;
                }
                if ($('input[name="salary_form"][value="range"]:checked').length > 0) {
                    if ($('#salary_min').val().trim() === '') {
                        alert('給与①最低賃金を入力してください。');
                        $('#salary_min').focus();
                        formIsValid = false;
                    }
                    if ($('#salary_max').val().trim() === '') {
                         alert('給与②最高賃金を入力してください。');
                         $('#salary_max').focus();
                         formIsValid = false;
                    }
                }
            }


            // エラーがある場合は送信阻止
    if (!formIsValid) {
        e.preventDefault();
        alert('以下の項目を確認してください：\n\n' + errorMessages.join('\n'));
        console.log('バリデーションエラー:', errorMessages); // デバッグ用
        return false;
    }

    console.log('バリデーション通過'); // デバッグ用
    return true;
});

    });
    </script>
</div>

<?php 
// 専用のフッターを読み込み 
include(get_stylesheet_directory() . '/agency-footer.php'); 
?>