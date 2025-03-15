<?php
function tunniplaan_shortcode($atts) {
    
    $atts = shortcode_atts(array(
        'grupp' => isset($_GET['group']) ? $_GET['group'] : '6969696969',
        'nadal' => date('Y-m-d', strtotime('monday this week')) 
    ), $atts, 'voco_tunniplaan');
    
    $week_date = date('Y-m-d', strtotime($atts['nadal'])) . 'T00:00:00';
    
    $url = "https://siseveeb.voco.ee/veebilehe_andmed/tunniplaan?nadal={$week_date}&grupp={$atts['grupp']}";
    
    $response = wp_remote_get($url);
    
    if (is_wp_error($response)) {
        return '<p>Viga tunniplaani laadimisel.</p>';
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (!$data || !isset($data['tunnid']) || !isset($data['ajad'])) {
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
    
    $meal_time = isset($data['ajad']['soomine']) ? $data['ajad']['soomine'] : '12:30-13:00';
    
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
    
    // Generate the full hours array
    $full_hours = array();
    for ($h = $start_hour; $h <= $end_hour; $h++) {
        $full_hours[] = sprintf('%02d:00', $h);
    }
    
    $output = '<div class="voco-tunniplaan">';
   
    
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
                
                if (lesson.algus === "11:55") {
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

function voco_groups_dropdown_shortcode() {
    $api_url = 'https://siseveeb.voco.ee/veebilehe_andmed/oppegrupid?seisuga=not_ended';
    $response = wp_remote_get($api_url);
    
    if (is_wp_error($response)) {
        return 'Error fetching data from API.';
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!isset($data['grupid']) || empty($data['grupid'])) {
        return 'No groups found.';
    }
    
    usort($data['grupid'], function($a, $b) {
        return strcmp($a['tahis'], $b['tahis']);
    });
    
    $output = '<div class="voco-group-selector">';
    $output .= '<label for="group-select">Vali tunniplaan:</label>';
    $output .= '<select id="group-select" class="voco-dropdown" onchange="window.location.href=this.value;">';
    $output .= '<option value="">-- Vali grupp --</option>';
    
    foreach ($data['grupid'] as $group) {
        $value = esc_url(add_query_arg('group', $group['id'], get_permalink()));
        $label = esc_html($group['tahis'] . ' ' . $group['oppekava']);
        $output .= '<option value="' . $value . '">' . $label . '</option>';
    }
    
    $output .= '</select>';
    $output .= '</div>';
    
    $output .= '<style>
        
    </style>';
    
    return $output;
}

add_shortcode('group_select', 'voco_groups_dropdown_shortcode');