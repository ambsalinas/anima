/**
 * While you were typing
 * Copyright (c) 2011 Aries-Belgium
 * Copyright (c) 2014 doylecc
 *
 *
 **/

jQuery(document).ready(function($)
{
	// add a container above the textfield
	var container = $(document.createElement('div')).attr('id', '#whiletyping_notifier').css('color','red');
	if($("#message"))
		container.insertBefore('#message');

	// add a periodical pull to check if there are new messages
	var current_script = THIS_SCRIPT.split('.')[0];
	var interval = setInterval(function() {
		$.ajax({
			url: 'xmlhttp.php?action=whiletyping&tid='+MYBB_TID+'&script='+current_script,
			type: 'get',
			success: function(response){
				container.html(response);
			}
		});
	}, 20000);


	// clear the whiletyping_notifier when the quick_reply_submit button is pressed
	$("#quick_reply_submit").on("click", function(){
		$("#whiletyping_notifier").html('');
		if (!navigator.userAgent.match(/msie/i) && !navigator.userAgent.match(/Trident.*rv\:11\./)) {
			$("#message").html('');
		}
	});

	// remove the whiletyping_notifier when the show new post link is clicked
	if(container)
	{
		container.on("click", function(){
			if(current_script == 'showthread')
			{
				whiletypingShowPosts();
			}
			container.html('');
		});
	}

});

function whiletypingShowPosts()
{
	jQuery.ajax({
		url: 'xmlhttp.php?action=whiletyping_get_posts&tid='+MYBB_TID,
		type: 'get',
		success: function(response){
			var posts_html = response;
			var pids = posts_html.match(/id="post_([0-9]+)"/g);
			var lastpid = pids.pop().match(/id="post_([0-9]+)"/);
			if(lastpid !== null) lastpid = lastpid[1];
			var posts = document.createElement("div");
			posts.html = posts_html;
			jQuery('#posts').append(posts.html);

			if(jQuery('#lastpid') && lastpid !== null)
			{
				jQuery('#lastpid').value = lastpid;
			}
		}
	});
}



function whiletypingSubmitPreview()
{
	whiletypingSimulateClick(jQuery("input[name='previewpost']")[0]);
}


jQuery.fn.scrollTo = function( target, options, callback ){
  if(typeof options == 'function' && arguments.length == 2){ callback = options; options = target; }
  var settings = $.extend({
    scrollTarget  : target,
    offsetTop     : 50,
    duration      : 500,
    easing        : 'swing'
  }, options);
  return this.each(function(){
    var scrollPane = $(this);
    var scrollTarget = (typeof settings.scrollTarget == "number") ? settings.scrollTarget : $(settings.scrollTarget);
    var scrollY = (typeof scrollTarget == "number") ? scrollTarget : scrollTarget.offset().top + scrollPane.scrollTop() - parseInt(settings.offsetTop);
    scrollPane.animate({scrollTop : scrollY }, parseInt(settings.duration), settings.easing, function(){
      if (typeof callback == 'function') { callback.call(this); }
    });
  });
}
