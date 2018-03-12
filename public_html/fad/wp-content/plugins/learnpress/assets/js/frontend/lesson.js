/**
 * Single Lesson functions
 *
 * @author ThimPress
 * @package LearnPress/JS
 * @version 1.1
 */
;(function ($) {

	var Lesson = function (args) {
		this.model = new Lesson.Model(args);
		this.view = new Lesson.View({
			model: this.model
		});
	}, windowTarget = parent.window || window;

	Lesson.Model = Backbone.Model.extend({
		initialize: function (args) {
			var timer = this._DHMS_to_seconds($('.countup').text());
			this.set('timer',timer);
		},

        _DHMS_to_seconds : function(s){
        	var unitsArray = s.split(":");
        	var units = { d : 0, h : 0, m : 0, s : 0};
        	if(unitsArray.length === 4){
        		units.d = Number(unitsArray[0]);
        		unitsArray=unitsArray.slice(1);
        	}else if(unitsArray.length === 3){
        		units.h = Number(unitsArray[0]);
        		unitsArray=unitsArray.slice(1);
        	}
        	units.m = Number(unitsArray[0]);
        	units.s= Number(unitsArray[1]);
        	return units.s + units.m*60 + units.h *60*60 + units.d * 60*60*24;
        }
	});
	Lesson.View = Backbone.View.extend({
		el                    : function () {
			return 'body';
		},
		events                : {
			'click .button-complete-item'			: '_completeItem',
			'click #lp-navigation .nav-link a'		: '_click_nav_link'
		},
		initialize: function (args) {
			this.initialize_countup();
		},
		_click_nav_link: function ( e ) {
			e.preventDefault();
			var $button = $(e.target);
			var lesson_id = '';
			if($button.prop("tagName").toLowerCase()!='a'){
				lesson_id = $button.parent().attr('data-id');
			} else {
				lesson_id = $($button).attr('data-id');
			}
			$(windowTarget.document).find('.course-item.course-item-'+lesson_id+'.viewable').trigger('click');
		},
		_completeItem      : function (e) {
			var that = this,
				$button = $(e.target),
				security = $button.data('security'),
				$item = $button.closest('.course-item');
			windowTarget.LP.blockContent();
			/*return;
			this.complete({
				security  : security,
				course_id : this.model.get('courseId'),
				callback  : function (response, item) {
					if (response.result == 'success') {
						// highlight item
						item.$el.removeClass('item-started').addClass('item-completed focus off');
						// then restore back after 3 seconds
						_.delay(function (item) {
							item.$el.removeClass('focus off');
						}, 3000, item);


						windowTarget.LP.setUrl(that.model.get('permalink'));
						var data = response.course_result;
						data.messageType = 'update-course';
						LP.sendMessage(data, windowTarget);
					}
					windowTarget.LP.unblockContent();
				}
			});*/
		},
		complete       : function (args) {
			var that = this;
			args = $.extend({
				context : null,
				callback: null,
				format  : 'json'
			}, this.model.toJSON(), args || {});
			var data = {};

			// Omit unwanted fields
			_.forEach(args, function (v, k) {
				if (($.inArray(k, ['content', 'current', 'title', 'url']) == -1) && !$.isFunction(v)) {
					data[k] = v;
				}
				;
			});
			LP.ajax({
				url     : this.model.get('url'),
				action  : 'complete-item',
				data    : data,
				dataType: 'json',
				success : function (response) {
					///response = LP.parseJSON(response);
					LP.Hook.doAction('learn_press_course_item_completed', response, that);
					response = LP.Hook.applyFilters('learn_press_course_item_complete_response', response, that);
					$.isFunction(args.callback) && args.callback.call(args.context, response, that);
				}
			});
		},
		_validateObject: function (obj) {
			var ret = {};
			for (var i in obj) {
				if (!$.isFunction(obj[i])) {
					ret[i] = obj[i];
				}
			}
			return ret;
		},
		
		initialize_countup : function(){
			_.bindAll(this, '_onTick');
			this._onTick();
		},
		updateCountup: function () {
            var totalTime = this._secondsToDHMS(this.model.get('timer')),
                strTime = [],
                units="(";
            if (totalTime.d) {
                strTime.push(this._addLeadingZero(totalTime.d));
                units=units.concat("g:");
            }
            if (totalTime.h) {
                strTime.push(this._addLeadingZero(totalTime.h));
                units=units.concat("h:");
            }
            strTime.push(this._addLeadingZero(totalTime.m));
            strTime.push(this._addLeadingZero(totalTime.s));
            units=units.concat("m:s)");
            this.$('.countup').html(strTime.join(':') + " " + units);
        },
        tryShowCompleteButton : function(){
        	var completeButton = this.$('.button-complete-item');
        	if( completeButton && completeButton.hasClass("hide") ){
        		completeButton.removeClass("hide");
        	}

        },
        _secondsToDHMS: function (t) {
            var d = Math.floor(t / (24 * 3600)), t = t - d * 24 * 3600, h = Math.floor(t / 3600), t = t - h * 3600,
                m = Math.floor(t / 60), s = Math.floor(t - m * 60);
            return {d: d, h: h, m: m, s: s}
        },
        _onTick: function () {
            this.model.set('timer',this.model.get('timer')+1);
            this.updateCountup();
            if( window.$LP_Lesson && this.model.get('timer') > window.$LP_Lesson.model.get('duration'))
            	this.tryShowCompleteButton();
            this.timeout = setTimeout(this._onTick, 1000);
        },
        _addLeadingZero: function (n) {
            return n < 10 ? "0" + n : "" + n;
        }
	});

	window.LP_Lesson = Lesson;
	$(document).ready(function () {
		if (typeof LP_Lesson_Params != 'undefined') {
			window.$LP_Lesson = new LP_Lesson($.extend({course: LP.$LP_Course}, LP_Lesson_Params));
		}
		windowTarget.LP.unblockContent();
	})

})(jQuery);