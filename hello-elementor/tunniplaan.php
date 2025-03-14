<?php
function tunniplaan_shortcode($atts) {
    $atts = shortcode_atts(array(
        'grupp' => '1692',
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
    
    // Get all unique lesson start times from the data
    $all_start_times = array();
    foreach ($data['tunnid'] as $date_lessons) {
        foreach ($date_lessons as $lesson) {
            $all_start_times[] = substr($lesson['algus'], 0, 5);
        }
    }
    $all_start_times = array_unique($all_start_times);
    sort($all_start_times);
    
    $standard_slots = array('08:00', '08:30', '09:00', '09:30', '10:00', '10:15', '10:30', 
                          '11:00', '11:30', '11:55', '12:00', '12:30', '13:00', '13:30', '14:00', '14:10', 
                          '14:30', '15:00', '15:30', '15:45', '16:00', '16:30', '17:00', '17:20', '17:30', 
                          '18:00', '18:30', '18:55', '19:00', '19:30', '20:00', '20:30', '20:35', '21:00', '21:30');
    
    $time_slots = array_unique(array_merge($all_start_times, $standard_slots));
    sort($time_slots);
    
    $output = '<div class="voco-tunniplaan">';
    $output .= '<table>';
    
    $output .= '<tr>';
    $output .= '<th>Aeg</th>'; 
    foreach ($days_of_week as $index => $day) {
        $date = $week_dates[$index];
        $formatted_date = date('d.m', strtotime($date));
        $output .= '<th>' . $day . '<br>' . $formatted_date . '</th>';
    }
    $output .= '</tr>';
    
    $lessons_by_day = array();
    foreach ($week_dates as $date_key) {
        $lessons_by_day[$date_key] = array();
        if (isset($data['tunnid'][$date_key])) {
            foreach ($data['tunnid'][$date_key] as $lesson) {
                $lessons_by_day[$date_key][] = $lesson;
            }
        }
    }
    
    // Time rows
    foreach ($time_slots as $time_key) {
        $output .= '<tr>';
        
        // Add time column first
        $output .= '<td class="time-column">' . $time_key . '</td>';
        
        // For each day of the week
        foreach ($week_dates as $date_key) {
            $output .= '<td class="time-cell" data-date="' . $date_key . '" data-time="' . $time_key . '">';
            
            // Check if there are lessons for this day
            if (isset($lessons_by_day[$date_key])) {
                foreach ($lessons_by_day[$date_key] as $lesson) {
                    $lesson_start = substr($lesson['algus'], 0, 5);
                    
                    // Only display lessons that start at this exact time
                    if ($lesson_start === $time_key) {
                        // Calculate lesson duration in minutes for styling
                        $start = new DateTime($lesson['algus']);
                        $end = new DateTime($lesson['lopp']);
                        $duration = ($end->getTimestamp() - $start->getTimestamp()) / 90;

                        $output .= '<div class="lesson" data-duration="' . $duration . '" data-lunch="'. ($end->getTimestamp() === 1741960800) .'">';
                        $output .= '<div class="lesson-time">' . substr($lesson['algus'], 0, 5) . '-' . substr($lesson['lopp'], 0, 5) . '</div>';
                        $output .= '<div class="lesson-name truncate" title="' . htmlspecialchars($lesson['aine']) . '">' . htmlspecialchars($lesson['aine']) . '</div>';
                        $output .= '<div class="lesson-room truncate" title="' . htmlspecialchars($lesson['ruum']) . '">' . htmlspecialchars($lesson['ruum']) . '</div>';
                        $output .= '<div class="lesson-teacher truncate" title="' . htmlspecialchars($lesson['opetaja']) . '">' . htmlspecialchars($lesson['opetaja']) . '</div>';
                        if ($end->getTimestamp() === 1741960800) {
                            $output .= '<div class="lunch-time">' . 'Söömine ' . $meal_time . '</div>';
                        }
                        $output .= '</div>';
                    }
                }
            }
            
            // Display lunch time
         /*    if ($time_key === substr($meal_time, 0, 5)) {
                $output .= '<div class="lunch-time">' . 'Söömine ' . $meal_time . '</div>';
            }
             */
            $output .= '</td>';
        }
        
        $output .= '</tr>';
    }
    
    $output .= '</table>';
    $output .= '</div>';
    
    $output .= '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const lessons = document.querySelectorAll(".voco-tunniplaan .lesson");
        
        lessons.forEach(function(lesson) {
            const duration = parseInt(lesson.getAttribute("data-duration"));
            const isLunch = lesson.getAttribute("data-lunch") === "1";
            let height = Math.max(60, duration * 2);
            if (isLunch) {
                height += 21
            }
            lesson.style.height = height + "px";
            
            const cell = lesson.parentElement;
            cell.style.position = "relative";
            
            lesson.style.position = "absolute";
            lesson.style.top = "5px";
            lesson.style.left = "5px";
            lesson.style.width = "calc(100% - 10px)";
            lesson.style.zIndex = "10";
            
            const lessonName = lesson.querySelector(".lesson-name").textContent;
            const lessonRoom = lesson.querySelector(".lesson-room").textContent;
            const lessonTeacher = lesson.querySelector(".lesson-teacher").textContent;
            lesson.setAttribute("title", `${lessonName}\n${lessonRoom}\n${lessonTeacher}`);
        });
    });
    </script>
    ';
    
    return $output;
}

add_shortcode('tunniplaan', 'tunniplaan_shortcode');