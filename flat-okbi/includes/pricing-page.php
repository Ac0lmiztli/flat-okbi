<?php
// includes/pricing-page.php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Рендерить сторінку "Керування цінами".
 */
function fok_render_pricing_page($fok_admin) {
    ?>
    <div class="wrap" id="fok-pricing-page">
        <h1><?php _e('Керування цінами на об\'єкти', 'okbi-apartments'); ?></h1>
        <p class="description">
            <?php _e('На цій сторінці ви можете швидко переглядати та редагувати ціни на нерухомість. Використовуйте фільтри, щоб знайти потрібні об\'єкти, внесіть зміни в полях "Ціна за м²" або "Загальна ціна" та натисніть "Зберегти зміни".', 'okbi-apartments'); ?>
        </p>
        <p class="description">
            <span class="dashicons dashicons-lock" style="vertical-align: text-bottom;"></span>
            <strong><?php _e('Пояснення іконки "Замочок":', 'okbi-apartments'); ?></strong>
            <?php _e('Ця іконка означає, що "Загальна ціна" на об\'єкт є <strong>фіксованою</strong> і не залежить від площі. Вона встановлюється, коли ви вручну редагуєте поле "Загальна ціна". Якщо іконки немає, ціна є <strong>динамічною</strong> і автоматично розраховується від "Ціни за м²".', 'okbi-apartments'); ?>
        </p>

        <div class="fok-pricing-filters card">
            <div class="fok-filter-item">
                <label for="fok_rc_filter"><?php _e('Житловий комплекс', 'okbi-apartments'); ?></label>
                <select id="fok_rc_filter">
                    <option value="0"><?php _e('Всі ЖК', 'okbi-apartments'); ?></option>
                    <?php
                    $all_rcs = $fok_admin->get_all_rcs_cached();
                    foreach ($all_rcs as $rc) {
                        echo '<option value="' . esc_attr($rc->ID) . '">' . esc_html($rc->post_title) . '</option>';
                    }
                    ?>
                </select>
            </div>
             <div class="fok-filter-item" id="fok-section-filter-wrapper" style="display: none;">
                <label for="fok_section_filter"><?php _e('Секція', 'okbi-apartments'); ?></label>
                <select id="fok_section_filter">
                    <option value="0"><?php _e('Всі секції', 'okbi-apartments'); ?></option>
                </select>
            </div>
            <div class="fok-filter-item">
                <label for="fok_property_type_filter"><?php _e('Тип нерухомості', 'okbi-apartments'); ?></label>
                <select id="fok_property_type_filter">
                    <option value="all"><?php _e('Всі типи', 'okbi-apartments'); ?></option>
                    <option value="apartment"><?php _e('Квартира', 'okbi-apartments'); ?></option>
                    <option value="commercial_property"><?php _e('Комерція', 'okbi-apartments'); ?></option>
                    <option value="parking_space"><?php _e('Паркомісце', 'okbi-apartments'); ?></option>
                    <option value="storeroom"><?php _e('Комора', 'okbi-apartments'); ?></option>
                </select>
            </div>
            <div class="fok-filter-item" id="fok-rooms-filter-wrapper" style="display: none;">
                <label for="fok_rooms_filter"><?php _e('Кількість кімнат', 'okbi-apartments'); ?></label>
                <select id="fok_rooms_filter">
                    <option value="all"><?php _e('Всі', 'okbi-apartments'); ?></option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5+">5+</option>
                </select>
            </div>
            <div class="fok-filter-actions">
                 <button type="button" class="button" id="fok-reset-filters"><?php _e('Скинути фільтри', 'okbi-apartments'); ?></button>
            </div>
        </div>

        <div id="fok-pricing-table-wrapper">
            <div class="fok-table-controls">
                <div class="fok-bulk-actions">
                    <label for="fok-bulk-action-selector" class="screen-reader-text"><?php _e('Вибрати масову дію', 'okbi-apartments'); ?></label>
                    <select id="fok-bulk-action-selector">
                        <option value="-1"><?php _e('Масові дії', 'okbi-apartments'); ?></option>
                        <option value="change_price"><?php _e('Змінити ціну', 'okbi-apartments'); ?></option>
                    </select>
                    <button type="button" class="button" id="fok-do-bulk-action"><?php _e('Застосувати', 'okbi-apartments'); ?></button>
                </div>
                <button class="button button-primary" id="fok-save-price-changes" disabled><?php _e('Зберегти зміни', 'okbi-apartments'); ?></button>
                <span id="fok-save-status"></span>
            </div>
            <table class="wp-list-table widefat fixed striped" id="fok-pricing-table">
                <thead>
                    <tr>
                        <th id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="fok-select-all"></th>
                        <th class="manage-column column-title sortable" data-sort-by="title"><a><span><?php _e('Об\'єкт', 'okbi-apartments'); ?></span></a></th>
                        <th class="manage-column"><?php _e('Тип', 'okbi-apartments'); ?></th>
                        <th class="manage-column sortable" data-sort-by="floor" data-sort-type="number"><a><span><?php _e('Поверх', 'okbi-apartments'); ?></span></a></th>
                        <th class="manage-column sortable" data-sort-by="area" data-sort-type="number"><a><span><?php _e('Площа, м²', 'okbi-apartments'); ?></span></a></th>
                        <th class="manage-column"><?php _e('Ціна за м²', 'okbi-apartments'); ?></th>
                        <th class="manage-column"><?php _e('Загальна ціна', 'okbi-apartments'); ?></th>
                    </tr>
                </thead>
                <tbody id="fok-pricing-table-body">
                   <tr><td colspan="6"><?php _e('Оберіть ЖК для початку роботи...', 'okbi-apartments'); ?></td></tr>
                </tbody>
                 <tfoot>
                    <tr>
                        <th class="manage-column column-cb check-column"></th> <th class="manage-column column-title"><?php _e('Об\'єкт', 'okbi-apartments'); ?></th>
                        <th class="manage-column"><?php _e('Тип', 'okbi-apartments'); ?></th>
                        <th class="manage-column"><?php _e('Поверх', 'okbi-apartments'); ?></th>
                        <th class="manage-column"><?php _e('Площа, м²', 'okbi-apartments'); ?></th>
                        <th class="manage-column"><?php _e('Ціна за м²', 'okbi-apartments'); ?></th>
                        <th class="manage-column"><?php _e('Загальна ціна', 'okbi-apartments'); ?></th>
                    </tr>
                </tfoot>
            </table>
            <div class="fok-loader-overlay" style="display: none;"><div class="spinner"></div></div>
        </div>
    </div>
    <div id="fok-price-modal-backdrop" style="display: none;">
        <div id="fok-price-modal-content" class="card">
            <h2><?php _e('Масова зміна цін', 'okbi-apartments'); ?></h2>
            <p><?php _e('Зміни будуть застосовані до <strong id="fok-modal-selected-count">0</strong> обраних об\'єктів.', 'okbi-apartments'); ?></p>
            
            <div class="fok-modal-form-group">
                <label>
                    <input type="radio" name="price_change_method" value="increase" checked>
                    <?php _e('Збільшити ціну на:', 'okbi-apartments'); ?>
                </label>
                <div class="fok-modal-input-group">
                    <input type="number" id="fok-increase-value" step="0.01">
                    <select id="fok-increase-unit">
                        <option value="percent">%</option>
                        <option value="amount"><?php _e('суму', 'okbi-apartments'); ?></option>
                    </select>
                    <select id="fok-increase-target" class="fok-change-target">
                        <option value="total"><?php _e('до загальної ціни', 'okbi-apartments'); ?></option>
                        <option value="sqm"><?php _e('до ціни за м²', 'okbi-apartments'); ?></option>
                    </select>
                </div>
            </div>

            <div class="fok-modal-form-group">
                <label>
                    <input type="radio" name="price_change_method" value="decrease">
                     <?php _e('Зменшити ціну на:', 'okbi-apartments'); ?>
                </label>
                 <div class="fok-modal-input-group">
                    <input type="number" id="fok-decrease-value" step="0.01">
                     <select id="fok-decrease-unit">
                        <option value="percent">%</option>
                        <option value="amount"><?php _e('суму', 'okbi-apartments'); ?></option>
                    </select>
                    <select id="fok-decrease-target" class="fok-change-target">
                        <option value="total"><?php _e('до загальної ціни', 'okbi-apartments'); ?></option>
                        <option value="sqm"><?php _e('до ціни за м²', 'okbi-apartments'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="fok-modal-form-group">
                <label>
                    <input type="radio" name="price_change_method" value="set_sqm">
                    <?php _e('Встановити нову ціну за м²:', 'okbi-apartments'); ?>
                </label>
                 <div class="fok-modal-input-group">
                    <input type="number" id="fok-set-sqm-value">
                </div>
            </div>

            <div class="fok-modal-form-group">
                <label>
                    <input type="radio" name="price_change_method" value="set_total">
                    <?php _e('Встановити нову загальну ціну:', 'okbi-apartments'); ?>
                </label>
                 <div class="fok-modal-input-group">
                    <input type="number" id="fok-set-total-value">
                </div>
            </div>
            
            <div class="fok-modal-actions">
                <button type="button" class="button button-primary" id="fok-modal-apply-btn"><?php _e('Застосувати зміни', 'okbi-apartments'); ?></button>
                <button type="button" class="button" id="fok-modal-cancel-btn"><?php _e('Скасувати', 'okbi-apartments'); ?></button>
            </div>
        </div>
    </div>
    <?php
} 