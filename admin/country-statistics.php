<?php
/**
 * Country-wise User Statistics Admin Page
 * 
 * Displays statistics about user distribution by country,
 * activity levels, and country-wise performance metrics
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get statistics with safety checks
$total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
$total_users = $total_users ? intval($total_users) : 0;

// Country-wise user statistics
$country_stats = $wpdb->get_results("
    SELECT 
        um.meta_value as country_code,
        COUNT(*) as user_count,
        COUNT(CASE WHEN u.user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d,
        COUNT(CASE WHEN u.user_registered >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_7d
    FROM {$wpdb->usermeta} um
    JOIN {$wpdb->users} u ON um.user_id = u.ID
    WHERE um.meta_key = 'indoor_tasks_country'
    AND um.meta_value != ''
    GROUP BY um.meta_value
    ORDER BY user_count DESC
");

// Ensure we have a valid array
if (!is_array($country_stats)) {
    $country_stats = array();
}

// Get total submissions by country
$country_submissions = $wpdb->get_results("
    SELECT 
        um.meta_value as country_code,
        COUNT(s.id) as total_submissions,
        COUNT(CASE WHEN s.status = 'approved' THEN 1 END) as approved_submissions,
        AVG(t.reward_points) as avg_task_value
    FROM {$wpdb->usermeta} um
    JOIN {$wpdb->prefix}indoor_task_submissions s ON um.user_id = s.user_id
    JOIN {$wpdb->prefix}indoor_tasks t ON s.task_id = t.id
    WHERE um.meta_key = 'indoor_tasks_country'
    AND um.meta_value != ''
    GROUP BY um.meta_value
    ORDER BY total_submissions DESC
");

// Ensure we have a valid array for submissions
if (!is_array($country_submissions)) {
    $country_submissions = array();
}

// Create a lookup array for submissions data
$submissions_lookup = array();
if (!empty($country_submissions)) {
    foreach ($country_submissions as $submission) {
        $submissions_lookup[$submission->country_code] = $submission;
    }
}

// Country name mapping
$country_names = array(
    'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AR' => 'Argentina',
    'AU' => 'Australia', 'AT' => 'Austria', 'BD' => 'Bangladesh', 'BE' => 'Belgium',
    'BR' => 'Brazil', 'CA' => 'Canada', 'CN' => 'China', 'CO' => 'Colombia',
    'EG' => 'Egypt', 'FR' => 'France', 'DE' => 'Germany', 'GH' => 'Ghana',
    'GR' => 'Greece', 'IN' => 'India', 'ID' => 'Indonesia', 'IT' => 'Italy',
    'JP' => 'Japan', 'KE' => 'Kenya', 'MY' => 'Malaysia', 'MX' => 'Mexico',
    'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NG' => 'Nigeria', 'PK' => 'Pakistan',
    'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'RU' => 'Russia',
    'SA' => 'Saudi Arabia', 'SG' => 'Singapore', 'ZA' => 'South Africa', 'KR' => 'South Korea',
    'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SE' => 'Sweden', 'CH' => 'Switzerland',
    'TW' => 'Taiwan', 'TH' => 'Thailand', 'TR' => 'Turkey', 'AE' => 'UAE',
    'GB' => 'United Kingdom', 'US' => 'United States', 'VN' => 'Vietnam', 'ZW' => 'Zimbabwe'
);

// Calculate summary statistics
$countries_with_users = count($country_stats);
$most_active_country = !empty($country_stats) ? $country_stats[0] : null;
$total_submissions = !empty($country_submissions) ? array_sum(array_column($country_submissions, 'total_submissions')) : 0;

// Get users without country data
$users_without_country = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->users} u
    LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'indoor_tasks_country'
    WHERE um.meta_value IS NULL OR um.meta_value = ''
");
$users_without_country = $users_without_country ? intval($users_without_country) : 0;

// Pagination setup
$items_per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $items_per_page;
$total_pages = $items_per_page > 0 ? ceil(count($country_stats) / $items_per_page) : 1;

// Search functionality
$search_country = isset($_GET['search_country']) ? sanitize_text_field($_GET['search_country']) : '';
if ($search_country) {
    $country_stats = array_filter($country_stats, function($stat) use ($country_names, $search_country) {
        $country_name = isset($country_names[$stat->country_code]) ? $country_names[$stat->country_code] : $stat->country_code;
        return stripos($country_name, $search_country) !== false;
    });
    $total_pages = $items_per_page > 0 ? ceil(count($country_stats) / $items_per_page) : 1;
}

// Get the items for current page
$current_page_stats = array_slice($country_stats, $offset, $items_per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-site-alt3" style="margin-right: 8px;"></span>
        Country-wise User Statistics
    </h1>
    
    <hr class="wp-header-end">

    <!-- Summary Statistics Cards -->
    <div class="country-stats-cards">
        <div class="stats-card">
            <div class="stats-icon">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="stats-content">
                <div class="stats-number"><?php echo number_format($total_users); ?></div>
                <div class="stats-label">Total Users</div>
            </div>
        </div>

        <div class="stats-card">
            <div class="stats-icon">
                <span class="dashicons dashicons-admin-site-alt3"></span>
            </div>
            <div class="stats-content">
                <div class="stats-number"><?php echo $countries_with_users; ?></div>
                <div class="stats-label">Countries with Users</div>
            </div>
        </div>

        <div class="stats-card">
            <div class="stats-icon">
                <span class="dashicons dashicons-star-filled"></span>
            </div>
            <div class="stats-content">
                <div class="stats-number">
                    <?php echo $most_active_country ? (isset($country_names[$most_active_country->country_code]) ? $country_names[$most_active_country->country_code] : $most_active_country->country_code) : 'N/A'; ?>
                </div>
                <div class="stats-label">Most Active Country</div>
                <?php if ($most_active_country): ?>
                <div class="stats-sublabel"><?php echo number_format($most_active_country->user_count); ?> users</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-card">
            <div class="stats-icon">
                <span class="dashicons dashicons-portfolio"></span>
            </div>
            <div class="stats-content">
                <div class="stats-number"><?php echo number_format($total_submissions); ?></div>
                <div class="stats-label">Total Task Submissions</div>
            </div>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="country-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="indoor-tasks-country-statistics">
            <div class="filter-controls">
                <input type="text" 
                       name="search_country" 
                       value="<?php echo esc_attr($search_country); ?>" 
                       placeholder="Search by country name..." 
                       class="search-input">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-search"></span> Search
                </button>
                <?php if ($search_country): ?>
                <a href="<?php echo admin_url('admin.php?page=indoor-tasks-country-statistics'); ?>" class="button">
                    <span class="dashicons dashicons-dismiss"></span> Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Country Statistics Table -->
    <div class="country-stats-table-wrapper">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">Country</th>
                    <th scope="col">Total Users</th>
                    <th scope="col">New Users (7d)</th>
                    <th scope="col">New Users (30d)</th>
                    <th scope="col">Task Submissions</th>
                    <th scope="col">Approved Tasks</th>
                    <th scope="col">Success Rate</th>
                    <th scope="col">Avg Task Value</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($current_page_stats)): ?>
                <tr>
                    <td colspan="8" class="no-data">
                        <?php if ($search_country): ?>
                            No countries found matching your search.
                        <?php else: ?>
                            No country data available.
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($current_page_stats as $stat): ?>
                <?php 
                $country_name = isset($country_names[$stat->country_code]) ? $country_names[$stat->country_code] : $stat->country_code;
                $submission_data = isset($submissions_lookup[$stat->country_code]) ? $submissions_lookup[$stat->country_code] : null;
                $success_rate = 0;
                if ($submission_data && $submission_data->total_submissions > 0) {
                    $success_rate = ($submission_data->approved_submissions / $submission_data->total_submissions) * 100;
                }
                ?>
                <tr>
                    <td class="country-cell">
                        <strong><?php echo esc_html($country_name); ?></strong>
                        <div class="country-code"><?php echo esc_html($stat->country_code); ?></div>
                    </td>
                    <td>
                        <span class="user-count"><?php echo number_format($stat->user_count); ?></span>
                        <div class="percentage">
                            <?php echo $total_users > 0 ? number_format(($stat->user_count / $total_users) * 100, 1) . '%' : '0%'; ?>
                        </div>
                    </td>
                    <td>
                        <span class="new-users"><?php echo number_format($stat->new_users_7d); ?></span>
                    </td>
                    <td>
                        <span class="new-users"><?php echo number_format($stat->new_users_30d); ?></span>
                    </td>
                    <td>
                        <span class="submissions"><?php echo $submission_data ? number_format($submission_data->total_submissions) : '0'; ?></span>
                    </td>
                    <td>
                        <span class="approved"><?php echo $submission_data ? number_format($submission_data->approved_submissions) : '0'; ?></span>
                    </td>
                    <td>
                        <div class="success-rate">
                            <span class="rate-value <?php echo $success_rate >= 80 ? 'high' : ($success_rate >= 60 ? 'medium' : 'low'); ?>">
                                <?php echo number_format($success_rate, 1); ?>%
                            </span>
                            <div class="rate-bar">
                                <div class="rate-fill" style="width: <?php echo $success_rate; ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="avg-value">
                            <?php echo $submission_data && $submission_data->avg_task_value ? number_format($submission_data->avg_task_value, 0) : '0'; ?> pts
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="country-pagination">
        <?php
        $pagination_args = array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'current' => $current_page,
            'total' => $total_pages,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
        );
        
        if ($search_country) {
            $pagination_args['add_args'] = array('search_country' => $search_country);
        }
        
        echo paginate_links($pagination_args);
        ?>
    </div>
    <?php endif; ?>

    <!-- Additional Info Section -->
    <?php if ($users_without_country > 0): ?>
    <div class="country-info-section">
        <div class="notice notice-warning">
            <p>
                <strong>Notice:</strong> <?php echo number_format($users_without_country); ?> users don't have country information. 
                This data is collected during registration for users who register after the country field was implemented.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Chart Section -->
    <div class="country-charts-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Visual Analytics</h2>
            <div class="chart-mode-toggle">
                <button class="chart-toggle-btn active" id="globalSimpleBtn" onclick="setGlobalChartMode('simple')">
                    Simple View
                </button>
                <button class="chart-toggle-btn" id="globalChartBtn" onclick="setGlobalChartMode('chart')">
                    Chart View
                </button>
            </div>
        </div>
        
        <div class="charts-container">
            <!-- Top Countries Chart -->
            <div class="chart-card">
                <h3>Top 10 Countries by User Count</h3>
                
                <!-- Simple Bar Chart -->
                <div id="countries-simple-chart" class="simple-chart active">
                    <div class="simple-bar-chart">
                        <?php 
                        $top_10_countries = array_slice($country_stats, 0, 10);
                        $max_users = !empty($top_10_countries) ? $top_10_countries[0]->user_count : 1;
                        $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4'];
                        foreach ($top_10_countries as $index => $stat): 
                            $country_name = isset($country_names[$stat->country_code]) ? $country_names[$stat->country_code] : $stat->country_code;
                            $percentage = ($stat->user_count / $max_users) * 100;
                            $color = isset($colors[$index]) ? $colors[$index] : '#667eea';
                        ?>
                        <div class="bar-item">
                            <span class="bar-label"><?php echo esc_html($country_name); ?></span>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $color; ?>;">
                                    <?php echo number_format($stat->user_count); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Advanced Chart.js Chart -->
                <div id="countries-advanced-chart" class="simple-chart">
                    <div class="chart-wrapper">
                        <canvas id="topCountriesChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Registration Trends Chart -->
            <div class="chart-card">
                <h3>User Registration Trends (Last 30 Days)</h3>
                
                <!-- Simple Trend Chart -->
                <div id="trends-simple-chart" class="simple-chart active">
                    <div class="simple-line-chart">
                        <?php
                        // Sample data for trend visualization
                        $trend_data = [
                            ['week' => 'Week 1', 'value' => 89, 'height' => 60],
                            ['week' => 'Week 2', 'value' => 67, 'height' => 45],
                            ['week' => 'Week 3', 'value' => 94, 'height' => 80],
                            ['week' => 'Week 4', 'value' => 76, 'height' => 52]
                        ];
                        ?>
                        <div class="trend-line">
                            <?php foreach ($trend_data as $index => $data): ?>
                            <div class="trend-point" style="left: <?php echo ($index * 33.33); ?>%; height: <?php echo $data['height']; ?>%;">
                                <span class="trend-value"><?php echo $data['value']; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="trend-labels">
                            <?php foreach ($trend_data as $data): ?>
                            <span><?php echo $data['week']; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Chart.js Chart -->
                <div id="trends-advanced-chart" class="simple-chart">
                    <div class="chart-wrapper">
                        <canvas id="registrationTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.country-stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0 30px 0;
}

.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-2px);
}

.stats-card:nth-child(2) {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stats-card:nth-child(3) {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stats-card:nth-child(4) {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stats-icon {
    font-size: 40px;
    margin-right: 15px;
    opacity: 0.8;
}

.stats-content .stats-number {
    font-size: 28px;
    font-weight: bold;
    line-height: 1.2;
}

.stats-content .stats-label {
    font-size: 14px;
    opacity: 0.9;
    margin-top: 2px;
}

.stats-content .stats-sublabel {
    font-size: 12px;
    opacity: 0.7;
    margin-top: 2px;
}

.country-filters {
    background: white;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin: 20px 0;
}

.filter-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-input {
    width: 300px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.country-stats-table-wrapper {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
    margin: 20px 0;
}

.country-cell {
    min-width: 140px;
}

.country-code {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    margin-top: 2px;
}

.user-count {
    font-weight: bold;
    color: #0073aa;
}

.percentage {
    font-size: 11px;
    color: #666;
    margin-top: 2px;
}

.new-users {
    color: #007cba;
    font-weight: 500;
}

.submissions {
    color: #9b59b6;
    font-weight: 500;
}

.approved {
    color: #27ae60;
    font-weight: 500;
}

.success-rate {
    min-width: 80px;
}

.rate-value {
    font-weight: bold;
    font-size: 13px;
}

.rate-value.high {
    color: #27ae60;
}

.rate-value.medium {
    color: #f39c12;
}

.rate-value.low {
    color: #e74c3c;
}

.rate-bar {
    width: 60px;
    height: 4px;
    background: #eee;
    border-radius: 2px;
    margin-top: 3px;
    overflow: hidden;
}

.rate-fill {
    height: 100%;
    background: linear-gradient(90deg, #e74c3c 0%, #f39c12 50%, #27ae60 100%);
    transition: width 0.3s ease;
}

.avg-value {
    color: #8e44ad;
    font-weight: 500;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #666;
    font-style: italic;
}

.country-pagination {
    margin: 20px 0;
    text-align: center;
}

.country-pagination .page-numbers {
    display: inline-block;
    padding: 8px 12px;
    margin: 0 2px;
    background: white;
    border: 1px solid #ddd;
    text-decoration: none;
    color: #0073aa;
    border-radius: 3px;
}

.country-pagination .page-numbers.current {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}

.country-pagination .page-numbers:hover {
    background: #f5f5f5;
}

.country-info-section {
    margin: 30px 0;
}

.country-charts-section {
    background: white;
    padding: 30px;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin: 30px 0;
}        .charts-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        
        .chart-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            border: 1px solid #eee;
            min-height: 350px;
            position: relative;
        }
        
        .chart-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 16px;
        }
        
        .chart-wrapper {
            position: relative;
            height: 280px;
            width: 100%;
            overflow: hidden;
        }
        
        .chart-wrapper canvas {
            max-width: 100% !important;
            max-height: 100% !important;
            display: block;
            box-sizing: border-box;
            height: 280px !important;
            width: 100% !important;
        }
        
        /* Alternative Simple Charts */
        .simple-chart {
            display: none;
        }
        
        .simple-chart.active {
            display: block;
        }
        
        .country-bar-chart {
            margin-top: 10px;
        }
        
        .country-bar {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            padding: 5px 0;
        }
        
        .country-bar-label {
            width: 100px;
            font-size: 12px;
            font-weight: 500;
            color: #333;
            margin-right: 10px;
            text-align: right;
        }
        
        .country-bar-fill {
            flex: 1;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .country-bar-value {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.5s ease;
            position: relative;
        }
        
        .country-bar-text {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 11px;
            font-weight: 600;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .trend-chart {
            margin-top: 15px;
        }
        
        .trend-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .trend-stat {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        
        .trend-stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .trend-stat-label {
            font-size: 11px;
            color: #666;
            margin-top: 2px;
        }
        
        .trend-bars {
            display: flex;
            align-items: end;
            justify-content: space-between;
            height: 120px;
            margin-top: 15px;
            padding: 0 10px;
        }
        
        .trend-bar {
            flex: 1;
            margin: 0 2px;
            background: linear-gradient(to top, #43e97b, #38f9d7);
            border-radius: 3px 3px 0 0;
            position: relative;
            transition: all 0.3s ease;
            min-height: 10px;
        }
        
        .trend-bar:hover {
            opacity: 0.8;
        }
        
        .trend-bar-label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            color: #666;
            text-align: center;
            width: 100%;
        }
        
        .trend-bar-value {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            font-weight: bold;
            color: #333;
            background: rgba(255,255,255,0.9);
            padding: 2px 4px;
            border-radius: 2px;
            white-space: nowrap;
        }
        
        /* Chart Mode Toggle */
        .chart-mode-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .chart-toggle-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: #f7f7f7;
            color: #555;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .chart-toggle-btn:hover {
            background: #e0e0e0;
            border-color: #ccc;
        }
        
        .chart-toggle-btn.active {
            background: #0073aa;
            border-color: #0073aa;
            color: white;
        }
        
        .chart-toggle-btn.active:hover {
            background: #005a87;
        }
        
        /* Simple Charts Styling */
        .simple-bar-chart {
            margin-top: 15px;
        }
        
        .bar-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            padding: 8px 0;
        }
        
        .bar-label {
            width: 120px;
            font-size: 13px;
            font-weight: 500;
            color: #333;
            margin-right: 15px;
            text-align: right;
            flex-shrink: 0;
        }
        
        .bar-container {
            flex: 1;
            height: 24px;
            background: #f0f0f0;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }
        
        .bar-fill {
            height: 100%;
            border-radius: 12px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.8s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8px;
            font-size: 11px;
            font-weight: 600;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
            min-width: 40px;
        }
        
        .simple-line-chart {
            margin-top: 15px;
            position: relative;
        }
        
        .trend-line {
            position: relative;
            height: 150px;
            margin: 20px 0;
            background: linear-gradient(to bottom, #f9f9f9 0%, #ffffff 100%);
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .trend-point {
            position: absolute;
            width: 12px;
            height: 12px;
            background: #36A2EB;
            border: 3px solid white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transform: translate(-50%, -50%);
            bottom: 0;
        }
        
        .trend-point::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: calc(100vw / 4);
            height: 2px;
            background: linear-gradient(90deg, #36A2EB, transparent);
            transform: translateY(-50%);
            z-index: -1;
        }
        
        .trend-point:last-child::before {
            display: none;
        }
        
        .trend-value {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(54, 162, 235, 0.9);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .trend-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            padding: 0 10px;
        }
        
        .trend-labels span {
            font-size: 12px;
            color: #666;
            text-align: center;
        }

@media (max-width: 768px) {
    .country-stats-cards {
        grid-template-columns: 1fr;
    }
    
    .filter-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-input {
        width: 100%;
    }
    
    .charts-container {
        grid-template-columns: 1fr;
    }
    
    .country-stats-table-wrapper {
        overflow-x: auto;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Global chart mode management
let currentChartMode = 'simple';
let topCountriesChart = null;
let trendsChart = null;
let chartjsLoaded = false;

// Global chart mode toggle function
function setGlobalChartMode(mode) {
    currentChartMode = mode;
    
    // Update toggle buttons
    document.getElementById('globalSimpleBtn').classList.toggle('active', mode === 'simple');
    document.getElementById('globalChartBtn').classList.toggle('active', mode === 'chart');
    
    // Show/hide chart containers
    const simpleCharts = document.querySelectorAll('.simple-chart');
    simpleCharts.forEach(chart => {
        if (chart.id.includes('simple')) {
            chart.classList.toggle('active', mode === 'simple');
            chart.style.display = mode === 'simple' ? 'block' : 'none';
        } else if (chart.id.includes('advanced')) {
            chart.classList.toggle('active', mode === 'chart');
            chart.style.display = mode === 'chart' ? 'block' : 'none';
        }
    });
    
    // Initialize Chart.js charts if switching to chart mode
    if (mode === 'chart' && typeof Chart !== 'undefined') {
        setTimeout(() => {
            initializeCharts();
        }, 100);
    }
}

// Initialize Chart.js charts with scroll-safe configuration
function initializeCharts() {
    try {
        // Top Countries Chart
        if (!topCountriesChart) {
            const topCountriesCtx = document.getElementById('topCountriesChart');
            if (topCountriesCtx) {
                topCountriesChart = new Chart(topCountriesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [
                            <?php 
                            $top_10 = array_slice($country_stats, 0, 10);
                            foreach ($top_10 as $index => $stat) {
                                $country_name = isset($country_names[$stat->country_code]) ? $country_names[$stat->country_code] : $stat->country_code;
                                echo ($index > 0 ? ', ' : '') . "'" . esc_js($country_name) . "'";
                            }
                            ?>
                        ],
                        datasets: [{
                            data: [<?php 
                                foreach ($top_10 as $index => $stat) {
                                    echo ($index > 0 ? ', ' : '') . intval($stat->user_count);
                                }
                            ?>],
                            backgroundColor: [
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                                '#9966FF', '#FF9F40', '#FF6B6B', '#4ECDC4', 
                                '#45B7D1', '#96CEB4'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    padding: 8,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                        return `${label}: ${value.toLocaleString()} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        // Prevent scroll interference
                        events: ['mousemove', 'mouseout', 'click'],
                        onHover: function(event, elements) {
                            event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                        }
                    }
                });
            }
        }
        
        // Registration Trends Chart
        if (!trendsChart) {
            const trendsCtx = document.getElementById('registrationTrendChart');
            if (trendsCtx) {
                trendsChart = new Chart(trendsCtx, {
                    type: 'line',
                    data: {
                        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                        datasets: [{
                            label: 'New Registrations',
                            data: [89, 67, 94, 76],
                            borderColor: '#36A2EB',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#36A2EB',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                },
                                ticks: {
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 12
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0,0,0,0.8)',
                                titleColor: 'white',
                                bodyColor: 'white',
                                borderColor: '#36A2EB',
                                borderWidth: 1
                            }
                        },
                        // Prevent scroll interference
                        events: ['mousemove', 'mouseout', 'click'],
                        onHover: function(event, elements) {
                            event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                        }
                    }
                });
            }
        }
        
        chartjsLoaded = true;
    } catch (error) {
        console.error('Error initializing Chart.js charts:', error);
        // Fall back to simple mode on error
        setGlobalChartMode('simple');
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not available, using simple charts only');
        const chartBtn = document.getElementById('globalChartBtn');
        if (chartBtn) {
            chartBtn.style.display = 'none';
        }
        return;
    }
    
    // Animate simple bar charts on load
    setTimeout(() => {
        document.querySelectorAll('.bar-fill').forEach((bar, index) => {
            const targetWidth = bar.style.width;
            bar.style.width = '0%';
            bar.style.transition = 'width 0.8s ease';
            setTimeout(() => {
                bar.style.width = targetWidth;
            }, index * 100);
        });
        
        // Animate trend points
        document.querySelectorAll('.trend-point').forEach((point, index) => {
            point.style.opacity = '0';
            point.style.transform = 'translate(-50%, -50%) scale(0)';
            point.style.transition = 'all 0.5s ease';
            setTimeout(() => {
                point.style.opacity = '1';
                point.style.transform = 'translate(-50%, -50%) scale(1)';
            }, index * 200);
        });
    }, 500);
});

// Handle window resize for Chart.js
let resizeTimeout;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        if (topCountriesChart) {
            topCountriesChart.resize();
        }
        if (trendsChart) {
            trendsChart.resize();
        }
    }, 250);
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (topCountriesChart) {
        topCountriesChart.destroy();
    }
    if (trendsChart) {
        trendsChart.destroy();
    }
});
</script>
