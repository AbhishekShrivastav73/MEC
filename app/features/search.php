<?php
/** no direct access **/
defined('MECEXEC') or die();

/**
 * @author Webnus <info@webnus.net>
 */
class MEC_feature_search extends MEC_base
{
    /**
     * @var MEC_factory
     */
    public $factory;

    /**
     * @var MEC_main
     */
    public $main;

    /**
     * @var MEC_search
     */
    public $search;

    /**
     * @var array
     */
    public $settings;

    /**
     * Constructor method
     * @author Webnus <info@webnus.net>
     */
    public function __construct()
    {
        // Import MEC Factory
        $this->factory = $this->getFactory();

        // Import MEC Main
        $this->main = $this->getMain();

        // MEC Settings
        $this->settings = $this->main->get_settings();

        // Search Library
        $this->search = $this->getSearch();
    }

    /**
     * Initialize search feature
     * @author Webnus <info@webnus.net>
     */
    public function init()
    {
        // Search Shortcode
        $this->factory->shortcode('MEC_search_bar', [$this, 'search']);

        if (isset($this->settings['search_bar_ajax_mode']) && $this->settings['search_bar_ajax_mode'] == '1')
        {
            $this->factory->action('wp_ajax_mec_get_ajax_search_data', [$this, 'mec_get_ajax_search_data']);
            $this->factory->action('wp_ajax_nopriv_mec_get_ajax_search_data', [$this, 'mec_get_ajax_search_data']);
        }
        else if (!is_admin())
        {
            $this->factory->filter('pre_get_posts', [$this, 'mec_search_filter']);
        }

        // Search Narrow
        $this->factory->action('wp_ajax_mec_refine_search_items', [$this->search, 'refine']);
        $this->factory->action('wp_ajax_nopriv_mec_refine_search_items', [$this->search, 'refine']);
    }

    /**
     * Show taxonomy
     * @param string $taxonomy
     * @param string $icon
     * @return boolean|string
     */
    public function show_taxonomy($taxonomy, $icon)
    {
        $terms = get_terms($taxonomy, ['hide_empty' => false]);
        $out = '';

        if (is_wp_error($terms) || empty($terms)) return false;
        $taxonomy_name = ($taxonomy == apply_filters('mec_taxonomy_tag', '')) ? 'tag' : str_replace('mec_', '', $taxonomy);

        switch ($taxonomy_name)
        {
            // Message Category
            case 'category':
                $taxonomy_name = $this->main->m('taxonomy_category', esc_html__('Category', 'modern-events-calendar-lite'));
                $taxonomy_key = 'category';
                break;

            // Message Location
            case 'location':
                $taxonomy_name = $this->main->m('taxonomy_location', esc_html__('Location', 'modern-events-calendar-lite'));
                $taxonomy_key = 'location';
                break;

            // Message Organizer
            case 'organizer':
                $taxonomy_name = $this->main->m('taxonomy_organizer', esc_html__('Organizer', 'modern-events-calendar-lite'));
                $taxonomy_key = 'organizer';
                break;

            // Message Organizer
            case 'speaker':
                $taxonomy_name = $this->main->m('taxonomy_speaker', esc_html__('Speaker', 'modern-events-calendar-lite'));
                $taxonomy_key = 'speaker';
                break;

            // Message Tag
            case 'tag':
                $taxonomy_name = esc_html__('Tag', 'modern-events-calendar-lite');
                $taxonomy_key = 'tag';
                break;

            // Message label
            case 'label':
                $taxonomy_name = $this->main->m('taxonomy_label', esc_html__('Label', 'modern-events-calendar-lite'));
                $taxonomy_key = 'label';
                break;

            // Default Screen
            default:
                $taxonomy_name = str_replace('mec_', '', $taxonomy);
                $taxonomy_key = $taxonomy_name;
                break;
        }

        $out .= '<div class="mec-dropdown-search mec-select2-dropdown"><i class="mec-sl-' . esc_attr($icon) . '"></i>';
        $args = [
            'show_option_none' => $taxonomy_name,
            'option_none_value' => '',
            'orderby' => 'name',
            'order' => 'ASC',
            'show_count' => 0,
            'hide_empty' => 0,
            'include' => ((isset($taxonomy_name) and trim($taxonomy_name)) ? $taxonomy_name : ''),
            'echo' => false,
            'selected' => 0,
            'hierarchical' => true,
            'name' => $taxonomy_key,
            'taxonomy' => $taxonomy,
        ];

        $out .= wp_dropdown_categories($args);
        $out .= '</div>';

        return $out;
    }

    public function mec_get_ajax_search_data()
    {
        if (sanitize_text_field($_POST['length']) < '3')
        {
            esc_html_e('Please enter at least 3 characters and try again', 'modern-events-calendar-lite');
            die();
        }

        $mec_tag_query = null;
        $mec_queries = [];

        if (!empty($_POST['location']))
        {
            $location = sanitize_text_field($_POST['location']);
            $mec_queries[] = [
                'taxonomy' => 'mec_location',
                'field' => 'id',
                'terms' => [$location],
                'operator' => 'IN',
            ];
        }

        if (!empty($_POST['category']))
        {
            $category = sanitize_text_field($_POST['category']);
            $mec_queries[] = [
                'taxonomy' => 'mec_category',
                'field' => 'id',
                'terms' => [$category],
                'operator' => 'IN',
            ];
        }

        if (!empty($_POST['organizer']))
        {
            $organizer = sanitize_text_field($_POST['organizer']);
            $mec_queries[] = [
                'taxonomy' => 'mec_organizer',
                'field' => 'id',
                'terms' => [$organizer],
                'operator' => 'IN',
            ];
        }

        if (!empty($_POST['speaker']))
        {
            $speaker = sanitize_text_field($_POST['speaker']);
            $mec_queries[] = [
                'taxonomy' => 'mec_speaker',
                'field' => 'id',
                'terms' => [$speaker],
                'operator' => 'IN',
            ];
        }

        // Tag Method
        $tag_method = $this->settings['tag_method'] ?? 'post_tag';

        if (!empty($_POST['tag']))
        {
            if ($tag_method === 'post_tag')
            {
                $term = get_term_by('id', sanitize_text_field($_POST['tag']), apply_filters('mec_taxonomy_tag', ''));
                if ($term) $mec_tag_query = $term->slug;
            }
            else
            {
                $mec_queries[] = [
                    'taxonomy' => apply_filters('mec_taxonomy_tag', ''),
                    'field' => 'id',
                    'terms' => [sanitize_text_field($_POST['tag'])],
                    'operator' => 'IN',
                ];
            }
        }

        if (!empty($_POST['label']))
        {
            $label = sanitize_text_field($_POST['label']);
            $mec_queries[] = [
                'taxonomy' => 'mec_label',
                'field' => 'id',
                'terms' => [$label],
                'operator' => 'IN',
            ];
        }

        $args = [
            'tax_query' => $mec_queries,
            's' => sanitize_text_field($_POST['keyword']),
            'post_type' => $this->main->get_main_post_type(),
            'post_status' => ['publish'],
        ];

        if ($tag_method === 'post_tag' && $mec_tag_query) $args['tag'] = $mec_tag_query;

        // Query
        $the_query = new WP_Query($args);
        if ($the_query->have_posts())
        {
            while ($the_query->have_posts())
            {
                $the_query->the_post();
                include MEC::import('app.features.search_bar.search_result', true, true);
            }

            wp_reset_postdata();
        }
        else
        {
            include MEC::import('app.features.search_bar.search_noresult', true, true);
        }

        die();
    }

    /**
     * Search Filter
     * @param WP_Query $query
     * @return WP_Query $query
     */
    public function mec_search_filter($query)
    {
        // Do not change Query if it is not search page!
        if (!$query->is_search) return $query;

        // Do not do anything in Backend
        if (is_admin()) return $query;

        // Do not change anything in Rest API
        if (defined('REST_REQUEST')) return $query;

        // Do not change Query if it is not a search related to MEC!
        if ((is_array($query->get('post_type')) and !in_array($this->main->get_main_post_type(), $query->get('post_type'))) or (!is_array($query->get('post_type')) and $query->get('post_type') != 'mec-events')) return $query;

        $mec_tag_query = null;
        $mec_queries = [];

        if (!empty($_GET['location']))
        {
            $mec_queries[] = [
                'taxonomy' => 'mec_location',
                'field' => 'id',
                'terms' => [sanitize_text_field($_GET['location'])],
                'operator' => 'IN',
            ];
        }

        if (!empty($_GET['category']))
        {
            $mec_queries[] = [
                'taxonomy' => 'mec_category',
                'field' => 'id',
                'terms' => [sanitize_text_field($_GET['category'])],
                'operator' => 'IN',
            ];
        }

        if (!empty($_GET['organizer']))
        {
            $mec_queries[] = [
                'taxonomy' => 'mec_organizer',
                'field' => 'id',
                'terms' => [sanitize_text_field($_GET['organizer'])],
                'operator' => 'IN',
            ];
        }

        if (!empty($_GET['speaker']))
        {
            $mec_queries[] = [
                'taxonomy' => 'mec_speaker',
                'field' => 'id',
                'terms' => [sanitize_text_field($_GET['speaker'])],
                'operator' => 'IN',
            ];
        }

        // Tag Method
        $tag_method = $this->settings['tag_method'] ?? 'post_tag';

        if (!empty($_GET['tag']))
        {
            if ($tag_method === 'post_tag')
            {
                $term = get_term_by('id', sanitize_text_field($_GET['tag']), apply_filters('mec_taxonomy_tag', ''));
                if ($term) $mec_tag_query = $term->slug;
            }
            else
            {
                $mec_queries[] = [
                    'taxonomy' => apply_filters('mec_taxonomy_tag', ''),
                    'field' => 'id',
                    'terms' => [sanitize_text_field($_GET['tag'])],
                    'operator' => 'IN',
                ];
            }
        }

        if (!empty($_GET['label']))
        {
            $mec_queries[] = [
                'taxonomy' => 'mec_label',
                'field' => 'id',
                'terms' => [sanitize_text_field($_GET['label'])],
                'operator' => 'IN',
            ];
        }

        if ($mec_tag_query and $tag_method === 'post_tag') $query->set('tag', $mec_tag_query);
        else
        {
            $query->set('tag', null);
            $query->set('tag_slug__in', null);
        }

        if (count($mec_queries))
        {
            $query->set('tax_query', $mec_queries);
            $query->tax_query = $mec_queries;
        }

        return $query;
    }

    /**
     * Show user search bar
     * @return string
     */
    public function search()
    {
        $path = MEC::import('app.features.search_bar.search_bar', true, true);

        ob_start();
        include $path;
        return ob_get_clean();
    }
}
