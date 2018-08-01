<?php
add_action('gform_loaded', 'bbconnect_workqueues_gf_addon_launch');
function bbconnect_workqueues_gf_addon_launch() {
    GFForms::include_addon_framework();
    class GFBBConnectWorkQueues extends GFAddOn {
        protected $_version = BBCONNECT_WORKQUEUES_VERSION;
        protected $_min_gravityforms_version = '2.0';
        protected $_slug = 'bbconnect-workqueues';
        protected $_path = 'bbconnect-workqueues/forms.php';
        protected $_full_path = __FILE__;
        protected $_title = 'Gravity Forms Connexions Work Queues Integrations';
        protected $_short_title = 'Connexions Work Queues';
        private static $_instance = null;

        public function init() {
            // Custom form settings
            add_filter('gform_form_settings', array($this, 'custom_form_settings'), 10, 2);
            add_filter('gform_pre_form_settings_save', array($this, 'save_form_settings'));

            // Custom entry meta
            add_filter('gform_entries_field_value', array($this, 'filter_field_values'), 10, 4);
            add_filter('gform_entry_detail_meta_boxes', array($this, 'meta_box'), 10, 3);

            // Track actions
            add_action('gform_after_submission', array($this, 'track_action'), 10, 2);

            parent::init();
        }

        /**
         * Returns an instance of this class, and stores it in the $_instance property.
         *
         * @return object $_instance An instance of this class.
         */
        public static function get_instance() {
            if (self::$_instance == null) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        public function custom_form_settings($settings, $form) {
            $fields = $form['fields'];
            $field_options = '<option value=""'.selected(rgar($form, 'bbconnect_workqueues_field'), '', false).'>None</option>'."\n";

            foreach ($fields as $key => $field) {
                $field_options .= '<option value="'.$field['id'].'"'.selected(rgar($form, 'bbconnect_workqueues_field'), $field['id'], false).'>'.$field['label'].'</option>'."\n";
            }

            ob_start();
?>
        <tr>
            <td colspan="2">
                <h4 class="gf_settings_subgroup_title">Action Notes and Work Queues</h4>
            </td>
        </tr>
        <tr>
            <th><label for="bbconnect_workqueues_field">Work Queue</label></th>
            <td>
                <select name="bbconnect_workqueues_field" id="bbconnect_workqueues_field">
                    <?php echo $field_options; ?>
                </select>
                <script>
                    jQuery(document).ready(function() {
                        bbconnect_workqueues_settings_toggle();
                        jQuery('select#bbconnect_workqueues_field').change(function() {
                            bbconnect_workqueues_settings_toggle();
                        });
                    });

                    function bbconnect_workqueues_settings_toggle() {
                        if (jQuery('select#bbconnect_workqueues_field').val() == '') {
                            jQuery('tbody.bbconnect_workqueues_extra_settings').hide();
                        } else {
                            jQuery('tbody.bbconnect_workqueues_extra_settings').show();
                        }
                    }
                </script>
            </td>
        </tr>
        <tbody class="bbconnect_workqueues_extra_settings">
            <tr>
                <th>Action Type</th>
                <td><input type="radio" value="todo" <?php checked('todo', rgar($form, 'bbconnect_workqueues_status')); ?> name="bbconnect_workqueues_status" id="workqueue_status_todo">
                    <label class="inline" for="workqueue_status_todo">Task</label>
                    <input type="radio" value="todone" <?php checked('todone', rgar($form, 'bbconnect_workqueues_status')); ?> name="bbconnect_workqueues_status" id="workqueue_status_todone">
                    <label class="inline" for="workqueue_status_todone">Note</label>
                </td>
            </tr>
            <tr>
                <th><label for="mt-bbconnect_workqueues_description">Details</label></th>
                <td>
                    <span class="mt-bbconnect_workqueues_description" style="float: right; position: relative; right: 10px; top: 90px;"></span>
                    <?php wp_editor(rgar($form, 'bbconnect_workqueues_description'), 'bbconnect_workqueues_description', array('autop' => false, 'editor_class' => 'merge-tag-support mt-wp_editor mt-manual_position mt-position-right')); ?>
                </td>
            </tr>
        </tbody>
<?php
            $settings['Form Options']['bbconnect_workqueues'] = ob_get_clean();
            return $settings;
        }

        public function save_form_settings($form, $settings) {
            $form['bbconnect_workqueues_field'] = rgpost('bbconnect_workqueues_field');
            $form['bbconnect_workqueues_status'] = rgpost('bbconnect_workqueues_status');
            $form['bbconnect_workqueues_description'] = rgpost('bbconnect_workqueues_description');
            return $form;
        }

        public function get_entry_meta($entry_meta, $form_id) {
            $entry_meta['work_queue'] = array(
                    'label' => 'Work Queue',
                    'is_numeric' => false,
                    'is_default_column' => false,
                    'filter' => array(
                            'operators' => array(
                                    'is',
                                    'isnot',
                                    'contains',
                            ),
                    ),
            );
            $entry_meta['action_status'] = array(
                    'label' => 'Action Type',
                    'is_numeric' => false,
                    'is_default_column' => false,
                    'filter' => array(
                            'operators' => array(
                                    'is',
                                    'isnot',
                            ),
                            'choices' => $this->get_action_statuses(),
                    ),
            );
            $entry_meta['action_description'] = array(
                    'label' => 'Details',
                    'is_numeric' => false,
                    'is_default_column' => false,
            );

            return $entry_meta;
        }

        /**
         * Customise display of our custom meta
         * @param mixed $value
         * @param integer $form_id
         * @param mixed $field_id
         * @param array $entry
         * @return mixed
         */
        public function filter_field_values($value, $form_id, $field_id, $entry) {
            switch ($field_id) {
                case 'action_status':
                    if (!empty($value)) {
                        $value = $this->action_status_label($value);
                    }
                    break;
            }
            return $value;
        }

        /**
         * Add our custom meta box on entry details
         * @param array $meta_boxes The properties for the meta boxes.
         * @param array $entry The entry currently being viewed/edited.
         * @param array $form The form object used to process the current entry.
         * @return array
         */
        public function meta_box($meta_boxes, $entry, $form) {
            if (!empty($entry['action_status'])) {
                $meta_boxes[$this->_slug] = array(
                        'title'    => 'Action Notes',
                        'callback' => array($this, 'add_details_meta_box'),
                        'context'  => 'normal',
                );
            }
            return $meta_boxes;
        }

        /**
         * The callback used to echo the content to the meta box.
         *
         * @param array $args An array containing the form and entry objects.
         */
        public function add_details_meta_box($args) {
            $form = $args['form'];
            $entry = $args['entry'];
?>
            <table class="widefat fixed entry-detail-view" cellspacing="0">
                <tr>
                    <td class="entry-view-field-name">Work Queue</td>
                </tr>
                <tr>
                    <td class="entry-view-field-value"><?php echo $entry['work_queue']; ?></td>
                </tr>
                <tr>
                    <td class="entry-view-field-name">Action Type</td>
                </tr>
                <tr>
                    <td class="entry-view-field-value"><?php echo $this->action_status_label($entry['action_status']); ?></td>
                </tr>
                <tr>
                    <td class="entry-view-field-name">Details</td>
                </tr>
                <tr>
                    <td class="entry-view-field-value lastrow"><?php echo $entry['action_description']; ?></td>
                </tr>
            </table>
<?php
        }

        public function track_action($entry, $form) {
            if (!empty($form['bbconnect_workqueues_field']) && !empty($entry[$form['bbconnect_workqueues_field']])) {
                gform_update_meta($entry['id'], 'work_queue', $entry[$form['bbconnect_workqueues_field']]);
                gform_update_meta($entry['id'], 'action_status', $form['bbconnect_workqueues_status']);
                gform_update_meta($entry['id'], 'action_description', GFCommon::replace_variables($form['bbconnect_workqueues_description'], $form, $entry));
            }
        }

        private function get_action_statuses() {
            return array(
                    array(
                            'text' => 'Task',
                            'value' => 'todo',
                    ),
                    array(
                            'text' => 'Note',
                            'value' => 'todone',
                    ),
            );
        }

        private function action_status_label($value) {
            $statuses = $this->get_action_statuses();
            foreach ($statuses as $status) {
                if ($status['value'] == $value) {
                    return $status['text'];
                }
            }
            return $value;
        }
    }

    GFAddOn::register('GFBBConnectWorkQueues');
}
