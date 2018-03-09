<div id="learn-press-cert-wrap" class="<?php echo $has_template ? 'has-template' : ''; ?>">
	<div id="cert-design-view">
		<div id="cert-design-view-inside">
			<div id="cert-design-viewport">
				<?php if ( $has_template ): ?>
					<img src="<?php echo $has_template; ?>" class="cert-template" />
				<?php endif; ?>
			</div>
			<div id="no-template-selected">
				<h4 class="no-template-text"><?php _e( 'No template selected.', 'learnpress-certificates' ); ?></h4>
				<button class="button button-hero learn-press-select-template-button"><?php _e( 'Select Template', 'learnpress-certificates' ); ?></button>
				<h4 class="no-template-text-2"><?php _e( 'to starts design your favorite certificate now', 'learnpress-certificates' ); ?></h4>
			</div>
			<div id="cert-design-line-horizontal" class="cert-design-line horizontal"></div>
			<div id="cert-design-line-vertical" class="cert-design-line vertical"></div>
			<div id="cert-rulers">
				<div class="cert-ruler-horizontal">
					<span class="ruler-magnitude horizontal twenty-five">25%</span>
					<span class="ruler-magnitude horizontal fifty">50%</span>
					<span class="ruler-magnitude horizontal seventy-five">75%</span>
				</div>
				<div class="cert-ruler-horizontal bottom">
					<span class="ruler-magnitude horizontal twenty-five">25%</span>
					<span class="ruler-magnitude horizontal fifty">50%</span>
					<span class="ruler-magnitude horizontal seventy-five">75%</span>
				</div>
				<div class="cert-ruler-vertical">
					<span class="ruler-magnitude vertical twenty-five">25%</span>
					<span class="ruler-magnitude vertical fifty">50%</span>
					<span class="ruler-magnitude vertical seventy-five">75%</span>
				</div>
				<div class="cert-ruler-vertical right">
					<span class="ruler-magnitude vertical twenty-five">25%</span>
					<span class="ruler-magnitude vertical fifty">50%</span>
					<span class="ruler-magnitude vertical seventy-five">75%</span>
				</div>
			</div>
		</div>

	</div>
	<div id="cert-design-tools">
		<ul id="cert-design-fields">
			<li class="cert-design-field student-name" data-field="student-name" data-value="{student_name}">
				<a><?php _e( 'Student name', 'learnpress-certificates' ); ?></a>
				<span class="dashicons dashicons-plus"></span>
			</li>
			<li class="cert-design-field student-name" data-field="student-birth-place" data-value="{student_birth_place}">
				<a><?php _e( 'Student birth place', 'learnpress-certificates' ); ?></a>
				<span class="dashicons dashicons-plus"></span>
			</li>
			<li class="cert-design-field student-name" data-field="student-birth-date" data-value="{student_birth_date}">
				<a><?php _e( 'Student birth date', 'learnpress-certificates' ); ?></a>
				<span class="dashicons dashicons-plus"></span>
			</li>
			<li class="cert-design-field student-name" data-field="student-fiscal-code" data-value="{student_fiscal_code}">
				<a><?php _e( 'Student fiscal code', 'learnpress-certificates' ); ?></a>
				<span class="dashicons dashicons-plus"></span>
			</li>
			<li class="cert-design-field course-name" data-field="course-name" data-value="{course_name}">
				<a href=""><?php _e( 'Course name', 'learnpress-certificates' ); ?></a>
				<span class="dashicons dashicons-plus"></span>
			</li>
			<li class="cert-design-field course-start-date" data-field="course-start-date" data-value="{course_start_date}">
				<a href=""><?php _e( 'Course start date', 'learnpress-certificates' ); ?></a>
				<span class="dashicons dashicons-plus"></span>
			</li>
			<li class="cert-design-field course-end-date" data-field="course-end-date" data-value="{course_endn_date}">
				<a href=""><?php _e( 'Course end date', 'learnpress-certificates' ); ?></a>
				<span class="dashicons dashicons-plus"></span>
			</li>
			<li class="cert-design-field current-date" data-field="current-date" data-value="{current_date}">
				<a href=""><?php _e( 'Current date', 'learnpress-certificates' ); ?></a>
				<span class="dashicons dashicons-plus"></span>
			</li>
			<li class="cert-design-field current-time" data-field="current-time" data-value="{current_time}">
				<a href=""><?php _e( 'Current time', 'learnpress-certificates' ); ?></a>
				<span class="dashicons dashicons-plus"></span>
			</li>
			<li class="cert-design-field custom" data-field="custom">
				<a href=""><?php _e( 'Custom', 'learnpress-certificates' ); ?></a>
				<span class="dashicons dashicons-plus"></span>
			</li>
		</ul>
		<button class="button button-hero learn-press-select-template-button" type="button"><?php _e( 'Change template', 'learnpress-certificates' ); ?></button>
	</div>
	<div id="cert-design-field-settings" class="hide-if-js">
		<div id="cert-field-options">
			<h3 class="field-options-header">
				<span class="dashicons dashicons-admin-generic"></span>
				<span></span>
			</h3>
			<ul>

			</ul>
			<p>
				<button type="button" class="button" id="learn-press-close-settings-panel"><?php _e( 'Close', 'learnpress-certificates' ); ?></button>
				&nbsp;
				<button type="button" class="button cert-design-delete-layer" id="learn-press-delete-layer"><?php _e( 'Delete', 'learnpress-certificates' ); ?></button>
			</p>
		</div>
	</div>
</div>
<script type="text/javascript">
	jQuery(document).ready(function ($) {
		new Certificate.View(new Certificate.Model(<?php echo json_encode( $this->get_json( $post->ID ) );?>));
	});
</script>