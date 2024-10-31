<?php

class sccstslug_AdminPanel_Settings extends sccstslugDP_Admin_Panel {

    function __construct() {
       
        $this->menu_slug = SCCST_ADMIN_MENU_SLUG;
        global $screen_layout_columns;
        $screen_layout_columns = 2;
        parent::__construct(); 
    }

    function add_menu_pages() {
        $this->page_hook = add_menu_page(__('Nhận bài tự động từ sieucongcu.com', 'dp'), __('Nhận bài tự động', 'dp'), 'edit_themes', $this->menu_slug, array(&$this, 'menu_page'), 'dashicons-download', 61);
        add_filter('plugin_action_links_' . SCCST_BASENAME, array($this, 'add_action_link'), 10, 2);
        add_action( 'admin_print_scripts',  array($this, 'load_assets') );
        
    }
    
    function load_assets(){
        wp_register_script('dp-itembox', plugins_url('/../assets/itembox.js', __FILE__), array('jquery'), '', true);
        wp_enqueue_script('dp-itembox');
    }

    // thêm link chỗ plugin
    function add_action_link($links, $file) {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=' . $this->menu_slug)) . '">' . __('Cài đặt', 'db') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // thêm các box
    function add_meta_boxes() {
        add_meta_box('sccstslug-settings', __('Thêm nguồn nhận bài', 'dp'), array(&$this, 'meta_box'), $this->page_hook, 'normal');
    }

    function fields() {
        $default_home_sections = array();
        $cats = get_terms('category');
        foreach ($cats as $cat) {
            $default_home_sections[] = array('cat' => $cat->term_id, 'title' => $cat->name);
        }

        $fields = array(
            'sccstslug-settings' => array(// các fiels sẽ gắn vào box có id tương ứng
                array(
                    'name' => SCCST_OPT_NAME,
                    'callback' => array($this, 'show_items'),
                    'value' => $default_home_sections
                )
            )
        );
        return $fields;
    }

    function show_items() {
        $html = '
            <tr><td colspan="2">
            <div class="item-box">
            <p class="description" style="padding-bottom:10px;">'
                . __('Đừng quên nhấp chuột vào "<strong>Lưu lại</strong>".', 'dp')
                . '</p>
            <div class="item-list-container" id="dp-home-sections-item-list-container">
                    <a href="#" class="button add-new-item" data-position="prepend">' . __('Thêm 1 nguồn', 'dp') . '</a>
                    <ul class="item-list ui-sortable" id="dp-home-sections-item-list">';

        $items = get_option(SCCST_OPT_NAME);
        if (!empty($items) && is_array($items)) {
            foreach ($items as $number => $item) {
                $item = array_filter($item);
                if (!empty($item))
                    $html .= $this->show_item($number, $item);
            }
        }

        $html .= '
                    </ul>
                    <ul class="item-list-sample" id="dp-home-sections-item-list-sample" style="display:none;">' . $this->show_item() . '</ul>
            <a href="#" class="button add-new-item" data-position="append">' . __('Thêm 1 nguồn', 'dp') . '</a>

            </div>
            <p class="description" style="padding-bottom:10px;"><strong>'
                . __('Để nhận bài tự động từ sieucongcu.com bạn hãy làm theo các bước sau:', 'dp')
                . '</strong>'
                . '<br />' . __('Tạo các link post bài tự động. Cài đặt danh mục sẽ nhận, khóa an toàn.', 'dp')
                . '<br />' . __('Khóa an toàn có tác dụng không cho người khác post lên trang của bạn. Bạn cần giữ bí mật khóa này.', 'dp')
                . '<br />' . __('Lấy link post bài cùng với khóa được tạo ra để cài đặt trong trang post bài lên site vệ tinh của sieucongcu.com', 'dp')
                . '</p>
            </div>
            </td></tr>';

        return $html;
    }

    /**
     * Single section settings
     */
    function show_item($number = null, $item = array()) {
        $item = wp_parse_args($item, sccstslug_get_default_item_setting());
        $link_view = get_site_url() . "?page=autopostcontent&id=" . ($number + 1);
        if ($number === null) {
            $number = '##';
            $link_view = "";
        }

        $category_options = get_terms("category", array(
            'fields' => 'id=>name',
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => 10,
            'hide_empty' => '0',
            'hierarchical' => false
        ));
        $dropdown_categories = sccstslugDP_form_field(array(
            'echo' => 0,
            'type' => 'multiselect',
            'options' => $category_options,
            'name' => SCCST_OPT_NAME . '[' . $number . '][cat]',
            'value' => $item['cat']
        ));

        $section_title = __('Nhận bài cho', 'dp');
        $section_title .=!empty($item['title']) ? ': <spanc class="in-widget-title">' . $item['title'] . '</span>' : '';

        $html = '
	<li rel="' . $number . '" class="item-' . $number . '">
            <div class="section-box closed">
            <div class="section-handlediv" title="Click to toggle"><br></div>
            <h3 class="section-hndle"><span>' . $section_title . '</span></h3>
            <div class="section-inside">

            <table class="item-table">
                <tr>
                    <td>
                        <table class="item-table">
                            <tr>
                                <th><label>' . __('Link post', 'dp') . '</label></th> 
                                <td colspan="3">
                                        <a href="' . $link_view . '" target="_blank" style="display: inline">' . $link_view . '</a>
                                </td>
                            </tr>
                            <tr>
                                <th><label>' . __('Tiêu đề', 'dp') . '</label></th> 
                                <td colspan="3">
                                        <input class="widefat" type="text" value="' . $item['title'] . '" name="' . SCCST_OPT_NAME . '[' . $number . '][title]" />
                                </td>
                            </tr>
                            <tr>
                                <td><label>' . __('Lấy ảnh thumb:', 'dp') . '</label> </td>
                                <td>
                                    <input type="checkbox" value="1" name="' . SCCST_OPT_NAME . '[' . $number . '][take_thumb]" ' . ($item['take_thumb'] == 1 ? 'checked' : '') . ' />
                                </td>
                                <th rowspan="4"><label>' . __('Category:', 'dp') . '</label> </th>
                                <td rowspan="4">
                                    ' . $dropdown_categories . '&nbsp;&nbsp;
                                </td>
                            </tr>
                            <tr>
                                <th><label>' . __('Lấy ảnh trong bài:', 'dp') . '</label> </th>
                                <td>
                                    <input type="checkbox" value="1" name="' . SCCST_OPT_NAME . '[' . $number . '][take_image]" ' . ($item['take_image'] == 1 ? 'checked' : '') . ' />
                                </td>
                            </tr>
                            <tr>
                                <th><label>' . __('Trạng thái:', 'dp') . '</label> </th>
                                <td>
                                    <input type="radio" value="1" name="' . SCCST_OPT_NAME . '[' . $number . '][status]" ' . ($item['status'] == 1 ? 'checked' : '') . ' /> Công khai
                                    &nbsp; &nbsp; <input type="radio" value="2" name="' . SCCST_OPT_NAME . '[' . $number . '][status]" ' . ($item['status'] == 2 ? 'checked' : '') . ' /> Chờ duyệt
                                </td>
                            </tr>
                            <tr>
                                <th><label>' . __('Đăng bài sau:', 'dp') . '</label> </th>
                                <td>
                                    <input type="number" value="' . $item['time_delay'] . '" name="' . SCCST_OPT_NAME . '[' . $number . '][time_delay]" ' . ($item['time_delay'] == 1 ? 'checked' : '') . ' /> 
                                     (Phút)
                                </td>
                            </tr>
                            <tr>
                                <th><label>' . __('Khóa an toàn:', 'dp') . '</label> </th>
                                <td>
                                    <input type="text" value="' . $item['secret_key'] . '" name="' . SCCST_OPT_NAME . '[' . $number . '][secret_key]" class="secret_key" />
                                    <span class="button generate-key" rel="' . $number . '">Tạo khóa</span>
                                    <div>' . $item['secret_key'] . '</div>
                                </td>
                            </tr>
                        </table>
                    </td>

                    <td style="width:50px;">
                            <a href="#" class="button delete-item">' . __('Xóa', 'dp') . '</a>
                    </td>
                </tr>
            </table>
            </div>
            </div>
	</li>
	';

        return $html;
    }

}

if (is_admin()) {
    sccstslugDP_Admin_Panel::register("sccstslug_AdminPanel_Settings");
}

// dp_register_panel('sccstslug_AdminPanel_Settings');