<?php
/**
 * Fact-Check Leads Admin Page
 * View and manage collected emails
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Verify_Factcheck_Leads_Admin {
    
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'), 20);
        add_action('admin_post_ai_verify_export_leads', array(__CLASS__, 'export_leads'));
        add_action('admin_post_ai_verify_delete_lead', array(__CLASS__, 'delete_lead'));
    }
    
    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Fact-Check Leads',
            'Fact-Check Leads',
            'manage_options',
            'ai-verify-leads',
            array(__CLASS__, 'render_leads_page')
        );
    }
    
    /**
     * Render leads page
     */
    public static function render_leads_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
        // Get filter parameters
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        // Build query
        $where = array("user_email IS NOT NULL AND user_email != ''");
        
        if ($filter_status !== 'all') {
            $where[] = $wpdb->prepare("status = %s", $filter_status);
        }
        
        if ($filter_type !== 'all') {
            $where[] = $wpdb->prepare("input_type = %s", $filter_type);
        }
        
        if (!empty($search)) {
            $where[] = $wpdb->prepare(
                "(user_email LIKE %s OR user_name LIKE %s OR input_value LIKE %s)",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where_sql");
        $total_pages = ceil($total_items / $per_page);
        
        // Get leads
        $leads = $wpdb->get_results(
            "SELECT * FROM $table_name 
             WHERE $where_sql 
             ORDER BY created_at DESC 
             LIMIT $per_page OFFSET $offset",
            ARRAY_A
        );
        
        // Get statistics
        $stats = self::get_statistics();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">📧 Fact-Check Leads</h1>
            <a href="<?php echo admin_url('admin-post.php?action=ai_verify_export_leads'); ?>" class="page-title-action">
                Export All to CSV
            </a>
            <hr class="wp-header-end">
            
            <!-- Statistics Dashboard -->
            <div class="ai-verify-stats-dashboard" style="margin: 20px 0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="ai-verify-stat-card">
                        <div class="stat-icon" style="color: #acd2bf;">👥</div>
                        <div class="stat-value"><?php echo number_format($stats['total_leads']); ?></div>
                        <div class="stat-label">Total Leads</div>
                    </div>
                    
                    <div class="ai-verify-stat-card">
                        <div class="stat-icon" style="color: #4ade80;">✓</div>
                        <div class="stat-value"><?php echo number_format($stats['completed']); ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    
                    <div class="ai-verify-stat-card">
                        <div class="stat-icon" style="color: #fbbf24;">⏳</div>
                        <div class="stat-value"><?php echo number_format($stats['processing']); ?></div>
                        <div class="stat-label">Processing</div>
                    </div>
                    
                    <div class="ai-verify-stat-card">
                        <div class="stat-icon" style="color: #60a5fa;">📅</div>
                        <div class="stat-value"><?php echo number_format($stats['today']); ?></div>
                        <div class="stat-label">Today</div>
                    </div>
                    
                    <div class="ai-verify-stat-card">
                        <div class="stat-icon" style="color: #a78bfa;">📊</div>
                        <div class="stat-value"><?php echo number_format($stats['this_week']); ?></div>
                        <div class="stat-label">This Week</div>
                    </div>
                    
                    <div class="ai-verify-stat-card">
                        <div class="stat-icon" style="color: #f472b6;">📈</div>
                        <div class="stat-value"><?php echo number_format($stats['this_month']); ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <form method="get" style="margin: 20px 0;">
                <input type="hidden" name="page" value="ai-verify-leads">
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <select name="filter_status" style="min-width: 150px;">
                        <option value="all" <?php selected($filter_status, 'all'); ?>>All Statuses</option>
                        <option value="completed" <?php selected($filter_status, 'completed'); ?>>Completed</option>
                        <option value="processing" <?php selected($filter_status, 'processing'); ?>>Processing</option>
                        <option value="pending" <?php selected($filter_status, 'pending'); ?>>Pending</option>
                        <option value="failed" <?php selected($filter_status, 'failed'); ?>>Failed</option>
                    </select>
                    
                    <select name="filter_type" style="min-width: 150px;">
                        <option value="all" <?php selected($filter_type, 'all'); ?>>All Types</option>
                        <option value="url" <?php selected($filter_type, 'url'); ?>>URL</option>
                        <option value="title" <?php selected($filter_type, 'title'); ?>>Title</option>
                        <option value="phrase" <?php selected($filter_type, 'phrase'); ?>>Phrase</option>
                    </select>
                    
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search emails, names, content..." style="min-width: 250px;">
                    
                    <button type="submit" class="button">Filter</button>
                    
                    <?php if ($filter_status !== 'all' || $filter_type !== 'all' || !empty($search)): ?>
                        <a href="<?php echo admin_url('admin.php?page=ai-verify-leads'); ?>" class="button">Clear Filters</a>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Leads Table -->
            <?php if (empty($leads)): ?>
                <div class="notice notice-info" style="margin: 20px 0;">
                    <p><strong>No leads found.</strong> Leads will appear here once users submit the fact-check email form.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30px;">ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Input</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Score</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td><?php echo esc_html($lead['id']); ?></td>
                                
                                <td>
                                    <strong><?php echo esc_html($lead['user_name']); ?></strong>
                                </td>
                                
                                <td>
                                    <a href="mailto:<?php echo esc_attr($lead['user_email']); ?>">
                                        <?php echo esc_html($lead['user_email']); ?>
                                    </a>
                                </td>
                                
                                <td>
                                    <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo esc_html($lead['input_value']); ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <span class="badge" style="background: #e0e7ff; color: #4338ca; padding: 4px 8px; border-radius: 4px; font-size: 11px; text-transform: uppercase;">
                                        <?php echo esc_html($lead['input_type']); ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <?php echo self::get_status_badge($lead['status']); ?>
                                </td>
                                
                                <td>
                                    <?php if ($lead['overall_score']): ?>
                                        <strong style="color: <?php echo self::get_score_color($lead['overall_score']); ?>">
                                            <?php echo number_format($lead['overall_score'], 1); ?>%
                                        </strong>
                                    <?php else: ?>
                                        <span style="color: #9ca3af;">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <abbr title="<?php echo esc_attr($lead['created_at']); ?>">
                                        <?php echo human_time_diff(strtotime($lead['created_at']), current_time('timestamp')); ?> ago
                                    </abbr>
                                </td>
                                
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=ai-verify-leads&view=report&id=' . $lead['id']); ?>" class="button button-small">
                                        View
                                    </a>
                                    <a href="<?php echo admin_url('admin-post.php?action=ai_verify_delete_lead&id=' . $lead['id'] . '&_wpnonce=' . wp_create_nonce('delete_lead_' . $lead['id'])); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('Are you sure you want to delete this lead?');"
                                       style="color: #dc2626;">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php echo number_format($total_items); ?> items</span>
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $paged
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <style>
            .ai-verify-stat-card {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                text-align: center;
            }
            .stat-icon {
                font-size: 32px;
                margin-bottom: 10px;
            }
            .stat-value {
                font-size: 32px;
                font-weight: 700;
                color: #1f2937;
                margin-bottom: 5px;
            }
            .stat-label {
                font-size: 14px;
                color: #6b7280;
                font-weight: 500;
            }
        </style>
        <?php
    }
    
    /**
     * Get statistics
     */
    private static function get_statistics() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
        $stats = array(
            'total_leads' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_email IS NOT NULL"),
            'completed' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'"),
            'processing' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'processing'"),
            'today' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_email IS NOT NULL AND DATE(created_at) = CURDATE()"),
            'this_week' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_email IS NOT NULL AND YEARWEEK(created_at) = YEARWEEK(NOW())"),
            'this_month' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_email IS NOT NULL AND YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())")
        );
        
        return $stats;
    }
    
    /**
     * Get status badge HTML
     */
    private static function get_status_badge($status) {
        $badges = array(
            'completed' => array('color' => '#22c55e', 'bg' => '#dcfce7', 'text' => 'Completed'),
            'processing' => array('color' => '#eab308', 'bg' => '#fef9c3', 'text' => 'Processing'),
            'pending' => array('color' => '#3b82f6', 'bg' => '#dbeafe', 'text' => 'Pending'),
            'failed' => array('color' => '#ef4444', 'bg' => '#fee2e2', 'text' => 'Failed')
        );
        
        $badge = isset($badges[$status]) ? $badges[$status] : array('color' => '#6b7280', 'bg' => '#f3f4f6', 'text' => ucfirst($status));
        
        return sprintf(
            '<span style="background: %s; color: %s; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase;">%s</span>',
            $badge['bg'],
            $badge['color'],
            $badge['text']
        );
    }
    
    /**
     * Get score color
     */
    private static function get_score_color($score) {
        if ($score >= 80) return '#22c55e';
        if ($score >= 60) return '#84cc16';
        if ($score >= 40) return '#eab308';
        if ($score >= 20) return '#f97316';
        return '#ef4444';
    }
    
    /**
     * Export leads to CSV
     */
    public static function export_leads() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
        $leads = $wpdb->get_results(
            "SELECT user_name, user_email, input_type, input_value, status, overall_score, credibility_rating, created_at 
             FROM $table_name 
             WHERE user_email IS NOT NULL 
             ORDER BY created_at DESC",
            ARRAY_A
        );
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="factcheck-leads-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, array('Name', 'Email', 'Input Type', 'Input Value', 'Status', 'Score', 'Rating', 'Date'));
        
        // Data
        foreach ($leads as $lead) {
            fputcsv($output, array(
                $lead['user_name'],
                $lead['user_email'],
                $lead['input_type'],
                $lead['input_value'],
                $lead['status'],
                $lead['overall_score'] ? $lead['overall_score'] . '%' : '',
                $lead['credibility_rating'],
                $lead['created_at']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Delete lead
     */
    public static function delete_lead() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$id || !wp_verify_nonce($_GET['_wpnonce'], 'delete_lead_' . $id)) {
            wp_die('Invalid request');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_verify_factcheck_reports';
        
        $wpdb->delete($table_name, array('id' => $id));
        
        wp_redirect(admin_url('admin.php?page=ai-verify-leads&deleted=1'));
        exit;
    }
}

// Initialize
AI_Verify_Factcheck_Leads_Admin::init();