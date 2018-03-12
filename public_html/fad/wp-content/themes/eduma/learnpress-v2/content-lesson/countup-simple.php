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
error_log("post type" . $lesson->post->post_type);
if ( !$lesson || $lesson->post->post_type != LP_LESSON_CPT  ) {
	return;
}
$attended_time = $user->get_lesson_attended_time_html($lesson->ID);
?>
<div class="learnpress-countup-container">
		<style >
		
		.slideout {
			cursor: pointer;
			z-index : 1000000000000000000000000;
			position: fixed;
			top: 125px;
			right: 0;
			width: 38px;
			text-align: center;
			background: #6DAD53;
			-webkit-transition-duration: 0.3s;
			-moz-transition-duration: 0.3s;
			-o-transition-duration: 0.3s;
			transition-duration: 0.3s;
			-webkit-border-radius: 0 5px 5px 0;
			-moz-border-radius: 0 5px 5px 0;
			border-radius: 5px 0px 0px 5px;
			height: 75px;
		    display: flex;
		    padding-left:  20px;
		}
		.slideout_inner {
			position: fixed;
			top: 125px;
			right: -190px;
			background: #6DAD53;
			width: 190px;
			padding: 25px;
			height: 4px;
			-webkit-transition-duration: 0.3s;
			-moz-transition-duration: 0.3s;
			-o-transition-duration: 0.3s;
			transition-duration: 0.3s;
			text-align: left;
			-webkit-border-radius: 0 0 5px 0;
			-moz-border-radius: 0 0 5px 0;
			border-radius: 0 0 0 0;
			text-align: center;
			color:white;
		}
		.slideout.toggle {
			right: 187px;
			border-radius: 5px 5px 5px 5px;
		}
		.slideout.toggle .slideout_inner {
			right: 0;
		}
		.timer{
			    font-weight: bold;
			    transform: scale(0.9,1) rotate(-90deg);
			    color: white;
		}
		
	</style>
	<div class='slideout'>
		<div class='timer'><?php echo __( 'TIMER', 'learnpress' ); ?></div>
		<div class='slideout_inner'>
			<span class='fa fa-clock-o'></span>
    		<span class='countup'><?php echo $attended_time?></span>
	</div>
	<script type="text/javascript">
			jQuery('.learnpress-countup-container').click(function(){
			var classes = document.getElementsByClassName("slideout")[0].classList;
			if(classes.contains("toggle"))
				classes.remove("toggle")
			else
				classes.add("toggle")
	});
	</script>
	</div>
</div>
<script>
	//var element = document.getElementsByClassName("learnpress-countup-container")[0];
	//console.log(element);
	//jQuery('.learnpress-countup-container').detach().prependTo('body');
	//var old = document.querySelector('.content-item-only .learnpress-countup-container');
	//if(old)
	//	old.remove();
	var clear = function(event){
			var element = document.getElementsByClassName("learnpress-countup-container")[0];
			if(element)
				element.parentNode.removeChild(element);
	}
	window.addEventListener('onhashchange',clear,false);
	window.addEventListener('hashchange',clear,false);
	window.addEventListener('popstate',clear,false);
</script>