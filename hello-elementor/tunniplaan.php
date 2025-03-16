<?php
function tunniplaan_shortcode($atts) {
    
    $atts = shortcode_atts(array(
        'grupp' => isset($_GET['group']) ? $_GET['group'] : '6969696969',
        'ruum' => isset($_GET['room']) ? $_GET['room'] : NULL,
        'nadal' => isset($_GET['week']) ? $_GET['week'] : date('Y-m-d', strtotime('monday this week')) 
    ), $atts, 'voco_tunniplaan');
    
    $week_date = date('Y-m-d', strtotime($atts['nadal'])) . 'T00:00:00';
    
    $prev_week = date('Y-m-d', strtotime('-1 week', strtotime($atts['nadal'])));
    $next_week = date('Y-m-d', strtotime('+1 week', strtotime($atts['nadal'])));
    $current_week = date('Y-m-d', strtotime('monday this week'));
    
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $url_parts = parse_url($current_url);
    
    $params = array();
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $params);
    }
    
    function build_url_with_params($base_url, $params, $week_value) {
        $params['week'] = $week_value;
        $query = http_build_query($params);
        $url_parts = parse_url($base_url);
        $path = isset($url_parts['path']) ? $url_parts['path'] : '';
        return $path . '?' . $query;
    }
    
    $base_url = isset($url_parts['scheme']) ? $url_parts['scheme'] . '://' : '';
    $base_url .= isset($url_parts['host']) ? $url_parts['host'] : '';
    $base_url .= isset($url_parts['path']) ? $url_parts['path'] : '';
    
    if (isset($atts['ruum'])) {
        $url = "https://siseveeb.voco.ee/veebilehe_andmed/tunniplaan?nadal={$week_date}&ruum={$atts['ruum']}";
    } else {
        $url = "https://siseveeb.voco.ee/veebilehe_andmed/tunniplaan?nadal={$week_date}&grupp={$atts['grupp']}";
    }
    
    $response = wp_remote_get($url);
    
    if (is_wp_error($response)) {
        return '<p>Viga tunniplaani laadimisel.</p>';
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (!$data || !isset($data['tunnid'])) {
        return '<p>Andmed puuduvad või vigased.</p>';
    }
    
    $input_week = new DateTime($data['nadal']);
    $day_of_week = $input_week->format('N');
    
    $week_start = clone $input_week;
    if ($day_of_week != 1) {
        $days_to_subtract = $day_of_week - 1;
        $week_start->modify("-{$days_to_subtract} days");
    }
    
    $days_of_week = array(
        'Esmaspäev',
        'Teisipäev',
        'Kolmapäev',
        'Neljapäev',
        'Reede'
    );
    
    $week_dates = array();
    for ($i = 0; $i < 5; $i++) {
        $date = clone $week_start;
        $date->modify("+{$i} days");
        $week_dates[] = $date->format('Y-m-d');
    }
    if (isset($data['ajad'])) {
        $meal_time = isset($data['ajad']['soomine']) ? $data['ajad']['soomine'] : '12:30-13:00';
    }
    
    $start_hour = 7;
    $end_hour = 22;
    
    // Check data to find actual min and max hours
    foreach ($data['tunnid'] as $date => $lessons) {
        foreach ($lessons as $lesson) {
            $lesson_start_hour = (int)substr($lesson['algus'], 0, 2);
            $lesson_end_hour = (int)substr($lesson['lopp'], 0, 2);
            
            $start_hour = min($start_hour, $lesson_start_hour);
            $end_hour = max($end_hour, $lesson_end_hour);
        }
    }
    
    $full_hours = array();
    for ($h = $start_hour; $h <= $end_hour; $h++) {
        $full_hours[] = sprintf('%02d:00', $h);
    }
    
    $output = '<div class="voco-tunniplaan-navigation">';
    $output .= '<a href="' . build_url_with_params($base_url, $params, $prev_week) . '" class="week-nav prev-week">&larr; Eelmine nädal</a>';
    $output .= '<a href="' . build_url_with_params($base_url, $params, $current_week) . '" class="week-nav current-week">Käesolev nädal</a>';
    $output .= '<a href="' . build_url_with_params($base_url, $params, $next_week) . '" class="week-nav next-week">Järgmine nädal &rarr;</a>';
    $output .= '</div>';
    
    $week_start_formatted = date('d.m.Y', strtotime($week_dates[0]));
    $week_end_formatted = date('d.m.Y', strtotime($week_dates[4]));
    $output .= '<div class="voco-tunniplaan-current-week">Nädal: ' . $week_start_formatted . ' - ' . $week_end_formatted . '</div>';
    
    $output .= '<div class="voco-tunniplaan">';
    
    $output .= '<table>';
    
    $output .= '<tr>';
    $output .= '<th class="time-header">Aeg</th>'; 
    foreach ($days_of_week as $index => $day) {
        $date = $week_dates[$index];
        $formatted_date = date('d.m', strtotime($date));
        $output .= '<th>' . $day . '<br>' . $formatted_date . '</th>';
    }
    $output .= '</tr>';
    
    foreach ($full_hours as $index => $hour) {
        $hour_value = (int)substr($hour, 0, 2);
        $row_class = 'hour-row';
        
        $output .= '<tr class="' . $row_class . '">';
        
        $output .= '<td class="time-column">' . $hour . '</td>';
        
        foreach ($week_dates as $date_key) {
            $output .= '<td class="time-cell" data-date="' . $date_key . '" data-time="' . $hour . '" data-hour-index="' . $index . '">';
            
            $output .= '<div class="lesson-container"></div>';
            
            $output .= '</td>';
        }
        
        $output .= '</tr>';
    }
    
    $output .= '</table>';
    $output .= '</div>';
    
   
    $output .= '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const rows = document.querySelectorAll(".hour-row");
        const hourCells = {};
        
        rows.forEach(row => {
            const cells = row.querySelectorAll(".time-cell");
            cells.forEach(cell => {
                const date = cell.getAttribute("data-date");
                const time = cell.getAttribute("data-time");
                const hourIndex = parseInt(cell.getAttribute("data-hour-index"));
                
                if (!hourCells[date]) {
                    hourCells[date] = {};
                }
                hourCells[date][time] = {
                    cell: cell,
                    hourIndex: hourIndex
                };
            });
        });
        
        const lessonData = ' . json_encode($data['tunnid']) . ';
        const mealTime = "' . $meal_time . '";
        
        const cellHeight = 80;
        
        for (const date in lessonData) {
            for (const lesson of lessonData[date]) {
                const startHour = lesson.algus.substring(0, 2) + ":00";
                const endHour = lesson.lopp.substring(0, 2) + ":00";
                
                if (!hourCells[date] || !hourCells[date][startHour]) {
                    continue;
                }
                
                const startCell = hourCells[date][startHour].cell;
                const startIndex = hourCells[date][startHour].hourIndex;
                
                const startMinutes = parseInt(lesson.algus.substring(0, 2)) * 60 + parseInt(lesson.algus.substring(3, 5));
                const endMinutes = parseInt(lesson.lopp.substring(0, 2)) * 60 + parseInt(lesson.lopp.substring(3, 5));
                const durationHours = (endMinutes - startMinutes) / 60;
                
                const lessonElement = document.createElement("div");
                lessonElement.className = "lesson";
                
                lessonElement.innerHTML = `
                    <div class="lesson-time">${lesson.algus}-${lesson.lopp}</div>
                    <div class="lesson-name truncate" title="${lesson.aine}">${lesson.aine}</div>
                    <div class="lesson-room truncate" title="${lesson.ruum}">${lesson.ruum}</div>
                    <div class="lesson-teacher truncate" title="${lesson.opetaja}">${lesson.opetaja}</div>
                `;
                
                if (lesson.algus === "11:55" && mealTime) {
                    const lunchDiv = document.createElement("div");
                    lunchDiv.className = "lunch-time";
                    lunchDiv.textContent = "Söömine " + mealTime;
                    lessonElement.appendChild(lunchDiv);
                }
                
                const startTimeMinutes = parseInt(lesson.algus.substring(3, 5));
                const topOffset = (startTimeMinutes / 60) * cellHeight;
                
                lessonElement.style.top = topOffset + "px";
                lessonElement.style.height = (durationHours * cellHeight) + "px";
                
                startCell.querySelector(".lesson-container").appendChild(lessonElement);
                
                lessonElement.setAttribute("title", `${lesson.aine}\n${lesson.ruum}\n${lesson.opetaja}`);
            }
        }
    });
    </script>';
    
    return $output;
}

add_shortcode('tunniplaan', 'tunniplaan_shortcode');

function voco_dropdown_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'show' => 'both', // Options: 'groups', 'rooms', 'both'
        ),
        $atts,
        'voco_select'
    );
    
    $output = '';
    $show_groups = ($atts['show'] === 'groups' || $atts['show'] === 'both');
    $show_rooms = ($atts['show'] === 'rooms' || $atts['show'] === 'both');
    
    $current_group_id = isset($_GET['group']) ? sanitize_text_field($_GET['group']) : '';
    $current_room_id = isset($_GET['room']) ? sanitize_text_field($_GET['room']) : '';
    
    if ($show_groups) {
        $api_url = 'https://siseveeb.voco.ee/veebilehe_andmed/oppegrupid?seisuga=not_ended';
        $response = wp_remote_get($api_url);
        
        if (is_wp_error($response)) {
            $output .= '<p>Error fetching groups data from API.</p>';
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!isset($data['grupid']) || empty($data['grupid'])) {
                $output .= '<p>No groups found.</p>';
            } else {
                usort($data['grupid'], function($a, $b) {
                    return strcmp($a['tahis'], $b['tahis']);
                });
                
                $output .= '<div class="voco-group-selector">';
                $output .= '<label for="group-select">Vali tunniplaan:</label>';
                $output .= '<select id="group-select" class="voco-dropdown" onchange="window.location.href=this.value;">';
                $output .= '<option value="0">-- Vali grupp --</option>';
                
                $current_group_name = '';
                foreach ($data['grupid'] as $group) {
                    $value = esc_url(add_query_arg('group', $group['id'], remove_query_arg('room', get_permalink())));
                    $label = esc_html($group['tahis'] . ' ' . $group['oppekava']);
                    $selected = ($current_group_id == $group['id']) ? 'selected="selected"' : '';
                    
                    if ($selected) {
                        $current_group_name = $label;
                    }
                    
                    $output .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
                }
                
                $output .= '</select>';
                
               
                
                $output .= '</div>';
            }
        }
    }
    
    if ($show_rooms) {
        $rooms_json = file_get_contents(dirname(__FILE__) . '/ruumid.json');
        $rooms = json_decode($rooms_json, true);
        
        if (!$rooms) {
            $output .= '<p>Error loading rooms data.</p>';
        } else {
            asort($rooms);
            
            $output .= '<div class="voco-room-selector">';
            $output .= '<label for="room-select">Vali õpperuum:</label>';
            $output .= '<select id="room-select" class="voco-dropdown" onchange="window.location.href=this.value;">';
            $output .= '<option value="0">-- Vali ruum --</option>';
            
            $current_room_name = '';
            foreach ($rooms as $room_id => $room_name) {
                $value = esc_url(add_query_arg('room', $room_id, remove_query_arg('group', get_permalink())));
                $label = esc_html($room_name);
                $selected = ($current_room_id == $room_id) ? 'selected="selected"' : '';
                
                if ($selected) {
                    $current_room_name = $label;
                }
                
                $output .= '<option value="' . $value . '" ' . $selected . '>' . $label . '</option>';
            }
            
            $output .= '</select>';
            
           
            
            $output .= '</div>';
        }
    }
    
    return $output;
}
add_shortcode('tunniplaan_select', 'voco_dropdown_shortcode');