<?php
/**
 * Template for displaying countup of the lesson
 *
 * @package LearnPress/Templates
 * @author  SimoneMSR
 * @version 1.0
 */

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$user   = learn_press_get_current_user();
$course = LP()->global['course'];
$lesson   =  LP()->global['course-item'];
if ( !$lesson || $lesson->post->post_type != LP_LESSON_CPT  ) {
	return;
}
$attended_time = $user->get_lesson_attended_time_html($lesson->ID);
?>

<div class="quiz-clock">
	<div class="quiz-countdown quiz-timer">
		<i class="fa fa-clock-o"></i>
		<span class="quiz-text"><?php echo esc_html__( 'Time', 'eduma' ); ?></span>
		<div id="quiz-countdown" class="quiz-countdown">
			<div class="countup"><span><?php echo $attended_time ?></span></div>
		</div>
	</div>
</div>