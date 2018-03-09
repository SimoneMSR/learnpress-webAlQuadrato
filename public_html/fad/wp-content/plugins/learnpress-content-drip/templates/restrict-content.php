<?php
/**
 * Restrict lesson content template
 *
 * @author SimoneMSR
 */

/**
 * Format a timestamp to display its age (5 days ago, in 3 days, etc.).
 *
 * @param   int     $timestamp
 * @param   int     $now
 * @return  string
 */
function timetostr($timestamp, $now = null) {
    $age = ($now ?: time()) - $timestamp;
    $future = ($age < 0);
    $age = abs($age);

    $age = (int)($age / 60);        // minutes ago
    if ($age == 0) return $future ? "momentarily" : "just now";

    $scales = [
        ["minute", "minutes", 60],
        ["hour", "hours", 24],
        ["day", "days", 7],
        ["week", "weeks", 4.348214286],     // average with leap year every 4 years
        ["month", "months", 12],
        ["year", "years", 10],
        ["decade", "decades", 10],
        ["century", "centuries", 1000],
        ["millenium", "millenia", PHP_INT_MAX]
    ];

    foreach ($scales as list($singular, $plural, $factor)) {
        if ($age == 0)
            return $future
                ? "less than 1 $singular"
                : "less than 1 $singular ago";
        if ($age == 1)
            return $future
                ? "1 $singular"
                : "1 $singular ago";
        if ($age < $factor)
            return $future
                ? "$age $plural"
                : "$age $plural ago";
        $age = (int)($age / $factor);
    }
}


if(LP_Addon_Content_Drip::instance()->drip_type == 'preparatory_interval'){
	$date_available = timetostr(LP_Addon_Content_Drip::instance()->date_available + time());
	$message        = sprintf( __( 'Sorry! You can not view this lesson right now. It will become available after the previous lesson has been attended for %s', 'learnpress' ), $date_available );
}else{
	$date_format    = apply_filters( 'learn_press_restrict_content_date_format', get_option( 'date_format' ) );
	$date_available = date( $date_format, LP_Addon_Content_Drip::instance()->date_available );
	$message        = sprintf( __( 'Sorry! You can not view this lesson right now. It will become available on %s', 'learnpress' ), $date_available );
}


?>
<div class="learn-press-restrict-lesson-content">
	<?php learn_press_display_message( sprintf( '<p>%s</p>', $message ), 'error' ); ?>
</div>
