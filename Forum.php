<?php
class Forum {
	public $settings = array(
		'description' => 'A lightweight forum module for Billic.',
		'user_menu_name' => 'Community Forums',
		'user_menu_icon' => '<i class="icon-chat-bubble-two"></i>',
		'admin_menu_category' => 'Settings',
		'admin_menu_name' => 'Forum Manager',
		'admin_menu_icon' => '<i class="icon-chat-bubble-two"></i>',
		'allowed_tags' => '<p><a><strong><u><blockquote><ul><ol><li><h2><h3><s><em><img>',
		'permissions' => array(
			'Forum_Moderator'
		) ,
	);
	// User Ajax
	function user_ajax() {
		global $billic, $db;
		if (isset($_POST['ajaxFunc'])) {
			switch ($_POST['ajaxFunc']) {
				case 'savePost':
					$billic->disable_content();
					if (!$billic->user_has_permission($billic->user, 'Forum_Moderator')) {
						err('You do not have permission');
					}
					// SECURITY!!! Restrict HTML tags
					$body = strip_tags($_POST['html'], $this->settings['allowed_tags']);
					$body = preg_replace('/<([a-z]+)( href\="([a-z0-9\/\:\.\-\_\+\=]+)")?[^>]*>/ims', '<$1$2>', $body);
					$db->q('UPDATE `forum_posts` SET `body` = ? WHERE `id` = ?', $body, $_POST['postID']);
					echo json_encode(array(
						'status' => 'OK'
					));
					exit;
				break;
			}
		}
	}
	// View Topic
	function user_view_topic() {
		global $billic, $db;
		$topic = $db->q('SELECT * FROM `forum_topics` WHERE `id` = ?', $_GET['ID']);
		$topic = $topic[0];
		if (empty($topic)) {
			err('The topic does not exist');
		}
		$forum = $db->q('SELECT `name` FROM `forum_forums` WHERE `id` = ?', $topic['forumid']);
		$forum = $forum[0];
		echo '<ol class="breadcrumb"><li><a href="/User/Forum/">Forum</a></li><li><a href="/User/Forum/Action/ViewForum/ID/' . $topic['forumid'] . '/">' . safe($forum['name']) . '</a></li><li class="active">' . safe($topic['subject']) . '</li></ol>';
		echo '<h3><span class="label label-primary">' . safe($topic['subject']) . '</span></h3>';
		// Handle Reply
		if (!empty($billic->user) && isset($_POST['post_reply'])) {
			// SECURITY!!! Restrict HTML tags
			$body = strip_tags($_POST['html'], $this->settings['allowed_tags']);
			$body = preg_replace('/<([a-z]+)( href\="([a-z0-9\/\:\.\-\_\+\=]+)")?[^>]*>/ims', '<$1$2>', $body);
			if (empty($body)) {
				$billic->error('Message can not be empty');
			}
			if (empty($billic->errors)) {
				$db->insert('forum_posts', array(
					'topicid' => $topic['id'],
					'forumid' => $topic['forumid'],
					'body' => $body,
					'userid' => $billic->user['id'],
					'created' => time() ,
				));
				$billic->status = 'created';
				$billic->redirect('/User/Forum/Action/ViewTopic/ID/' . $topic['id'] . '/');
			}
		}
		$posts = $db->q('SELECT * FROM `forum_posts` WHERE `topicid` = ? ORDER BY `created` ASC', $topic['id']);
		foreach ($posts as $post) {
			echo '<table class="table table-striped" id="forumPost' . $post['id'] . '">';
			// Get the user of the post
			$user = $db->q('SELECT `id`, `firstname`, `lastname`, `email`, `permissions` FROM `users` WHERE `id` = ?', $post['userid']);
			$user = $user[0]; // first row because we are only getting 1 row
			echo '<tr><td rowspan="2" width="20%" class="forumview-left"><img src="https://en.gravatar.com/avatar/' . md5(strtolower($user['email'])) . '?s=150&d=mm&r=g" class="img-circle"><br><a href="/User/Forum/Action/ViewProfile/ID/' . $user['id'] . '/" class="forum-links">' . safe($user['firstname']) . ' ' . safe($user['lastname']) . '</a><br>';
			if ($billic->user_has_permission($user, 'admin')) {
				echo '<span class="label label-success">Staff</span>';
			} else if ($billic->user_has_permission($user, 'Forum_Moderator')) {
				echo '<span class="label label-info">Moderator</span>';
			} else {
				echo '<span class="label label-primary">User</span>';
			}
			echo '<br><br></td><td class="forumview-top">' . $billic->time_ago($post['created']) . ' ago';
			if ($billic->user_has_permission($billic->user, 'Forum_Moderator')) {
				echo '<button type="button" class="btn btn-primary btn-xs pull-right" onclick="forumEditPost(' . $post['id'] . ')" id="forumEditButton">Edit</button>';
			}
			echo '</td></tr><tr><td class="forumview-bottom" id="forumPostBody">' . $this->textWrap($post['body']) . '</td></tr>';
			echo '</table>';
		}
		$billic->show_errors();
		if (empty($billic->user)) {
			echo '<div class="alert alert-info" role="alert">To reply to this thread, please login.</div>';
		} else {
			echo '<table class="table table-striped"><tr class="forumreply"><th>Reply To Thread</th></tr>';
			echo '<tr class="forumreply"><td><form method="POST"><textarea class="ckeditor" name="html">' . safe($_POST['html']) . '</textarea></td></tr>';
			echo '<tr class="forumreply-bottom"><td><button type="submit" name="post_reply" class="btn btn-success">Reply</button</td></tr>';
			echo '</form></td></tr></table>';
			echo '<script src="//cdn.ckeditor.com/4.5.9/basic/ckeditor.js"></script>';
		}
		if ($billic->user_has_permission($billic->user, 'Forum_Moderator')) {
?>
<script>
	var forumEditOriginals = {};
	function forumEditPost(postID) {
		$('#forumPost'+postID+' #forumEditButton').hide();
		$('#forumPost'+postID+' #forumEditButton').after('<button type="button" class="btn btn-warning btn-xs pull-right" onclick="forumCancelEdit('+postID+')" id="forumCancelButton" style="margin-right:5px">Cancel</button>');
		$('#forumPost'+postID+' #forumEditButton').after('<button type="button" class="btn btn-success btn-xs pull-right" onclick="forumSaveEdit('+postID+')" id="forumSaveButton">Save</button>');
		forumEditOriginals[postID] = $('#forumPost'+postID+' #forumPostBody').html();
		$('#forumPost'+postID+' #forumPostBody').html('<textarea id="forumPostEditor'+postID+'">'+forumEditOriginals[postID]+'</textarea>');
		CKEDITOR.replace('forumPostEditor'+postID, {   
			disableNativeSpellChecker: false,
		});
	}
	function forumCancelEdit(postID) {
		$('#forumPost'+postID+' #forumEditButton').show();
		$('#forumPost'+postID+' #forumCancelButton').remove();
		$('#forumPost'+postID+' #forumSaveButton').remove();
		$('#forumPost'+postID+' #forumPostBody').html(forumEditOriginals[postID]);
		delete forumEditOriginals[postID];
	}
	function forumSaveEdit(postID) {
		var html = CKEDITOR.instances['forumPostEditor'+postID].getData();
		$.ajax({
			type: 'POST',
			url: '/User/Forum/',
			dataType: 'json',
			data: {
				ajaxFunc: 'savePost',
				postID: postID,
				html: html
			},
			success: function(data){
				if (data.status == 'OK') {
					forumEditOriginals[postID] = html;
					forumCancelEdit(postID);
				} else {
					alert('Error saving changes: '+data.status);
				}
			},
			error: function(xhr, textStatus, error){
				alert('There was an unexpected error while trying to save your changes');
				console.log(error);
				console.log(xhr.responseText);
			}
		});
	}
</script>
<?php
		}
	}
	// View Thread list
	function user_view_thread_list() {
		global $billic, $db;
		$forum = $db->q('SELECT * FROM `forum_forums` WHERE `id` = ?', $_GET['ID']);
		$forum = $forum[0];
		if (empty($forum)) {
			err('The forum does not exist');
		}
		echo '<ol class="breadcrumb"><li><a href="/User/Forum/">Forum</a></li><li class="active">' . safe($forum['name']) . '</li></ol>';
		echo '<a href="/User/Forum/Action/CreateThread/ForumID/' . $forum['id'] . '/"><button type="button" class="btn btn-success btn-sm pull-right"><i class="icon-plus"></i> Create a new Thread</button></a>';
		echo '<h3><span class="label label-primary">' . safe($forum['name']) . '</span></h3>';
		echo '<table class="table table-striped">
    <tr>
		<th>Subject</th>
		<th>Replies</th>
		<th>Views</th>
		<th>Last Post</th>
	</tr>';
		$topics = $db->q('SELECT * FROM `forum_topics` WHERE `forumid` = ?', $_GET['ID']);
		if (empty($topics)) {
			echo '<tr><td colspan="10">There are no topics in this forum.</td></tr>';
		}
		foreach ($topics as $topic) {
			$reply_count = $db->q('SELECT COUNT(*) FROM `forum_posts` WHERE `topicid` = ?', $topic['id']);
			$reply_count = $reply_count[0]['COUNT(*)']; // number of posts
			$reply_count = ($reply_count - 1); // First message doesn't count as a reply
			// Get id for last reply
			$last_reply = $db->q('SELECT `userid`, `created` FROM `forum_posts` WHERE `topicid` = ? ORDER BY `created` DESC LIMIT 1', $topic['id']);
			$last_reply = $last_reply[0]; // first row because we are only getting 1 row
			// Get the user of the last post
			$last_post_user = $db->q('SELECT `id`, `firstname`, `lastname` FROM `users` WHERE `id` = ?', $last_reply['userid']);
			$last_post_user = $last_post_user[0]; // first row because we are only getting 1 row
			echo '<tr><td><a href="/User/Forum/Action/ViewTopic/ID/' . urlencode($topic['id']) . '/" class="forum-links">' . safe($topic['subject']) . '</a></td><td>' . $reply_count . '</td><td>' . $topic['views'] . '</td><td>';
			if ($reply_count == 0 || empty($last_post_user)) {
				echo 'None';
			} else {
				echo $billic->time_ago($last_reply['created']) . ' ago by <a href="/User/Forum/Action/ViewProfile/ID/' . $last_post_user['id'] . '/" class="forum-links">' . $last_post_user['firstname'] . ' ' . $last_post_user['lastname'] . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}
	// Create Thread
	function user_create_thread() {
		global $billic, $db;
		if (empty($billic->user)) {
			echo '<div class="alert alert-info" role="alert">To create a new thread, please login.</div>';
			exit;
		}
		if (isset($_POST['create_thread'])) {
			// SECURITY!!! Restrict HTML tags
			$body = strip_tags($_POST['html'], $this->settings['allowed_tags']);
			$body = preg_replace('/<([a-z]+)( href\="([a-z0-9\/\:\.\-\_\+\=]+)")?[^>]*>/ims', '<$1$2>', $body);
			if (empty($_POST['subject'])) {
				$billic->error('Subject can not be empty');
			} else if (empty($body)) {
				$billic->error('Message can not be empty');
			}
			if (empty($billic->errors)) {
				$threadid = $db->insert('forum_topics', array(
					'forumid' => $_GET['ForumID'],
					'subject' => $_POST['subject'],
					'userid' => $billic->user['id'],
					'created' => time() ,
				));
				$db->insert('forum_posts', array(
					'topicid' => $threadid,
					'forumid' => $_GET['ForumID'],
					'body' => $body,
					'userid' => $billic->user['id'],
					'created' => time() ,
				));
				$billic->status = 'created';
				$billic->redirect('/User/Forum/Action/ViewThread/ID/' . $threadid . '/');
			}
		}
		$billic->show_errors();
		echo '<form method="POST"><table class="table table-striped">';
		echo '<tr><td>Subject</td><td><input type="text" name="subject" value="' . safe($_POST['subject']) . '" class="form-control"></td></tr>';
		echo '<tr><td>Message</td><td><textarea class="ckeditor" name="html">' . safe($_POST['html']) . '</textarea></td></tr>';
		echo '<tr><td colspan="2" align="center"><input type="submit" name="create_thread" value="Create &raquo;"></td></tr>';
		echo '</table></form>';
		echo '<script src="//cdn.ckeditor.com/4.4.7/basic/ckeditor.js"></script>';
	}
	// This section will be accessible at http://yourdomain.com/User/MyModule
	function user_area() {
		global $billic, $db;
		$this->user_ajax();
		if ($_GET['Action'] == 'ViewTopic') {
			$this->user_view_topic();
			return;
		}
		if ($_GET['Action'] == 'ViewForum') {
			$this->user_view_thread_list();
			return;
		}
		if ($_GET['Action'] == 'CreateThread') {
			$this->user_create_thread();
			return;
		}
		$this->user_forum_list();
	}
	// Forum List
	function user_forum_list() {
		global $billic, $db;
		echo '<h1>' . get_config('forum_name') . '</h1>';
		echo '<table class="table table-striped">
    <tr>
		<th colspan="2">FORUM</th>
		<th>TOPICS</th>
		<th>POSTS</th>
		<th>LAST POST</th>
	</tr>';
?>
<style>
	.forum-icon {
		font-size: 26px;
		width: 5px;
	}
</style>
<?php
		$forums = $db->q('SELECT * FROM `forum_forums`');
		foreach ($forums as $forum) {
			$topics_count = $db->q('SELECT COUNT(*) FROM `forum_topics` WHERE `forumid` = ?', $forum['id']);
			$topics_count = $topics_count[0]['COUNT(*)']; // number of topics
			$posts_count = $db->q('SELECT COUNT(*) FROM `forum_posts` WHERE `forumid` = ?', $forum['id']);
			$posts_count = $posts_count[0]['COUNT(*)']; // number of posts
			// Get id for last post
			$last_post = $db->q('SELECT `userid`, `topicid`, `created` FROM `forum_posts` WHERE `forumid` = ? ORDER BY `created` DESC LIMIT 1', $forum['id']);
			$last_post = $last_post[0]; // first row because we are only getting 1 row
			// Get the topic of the last post
			$last_post_topic = $db->q('SELECT `id`, `subject` FROM `forum_topics` WHERE `id` = ?', $last_post['topicid']);
			$last_post_topic = $last_post_topic[0]; // first row because we are only getting 1 row
			// Get the user of the last post
			$last_post_user = $db->q('SELECT `id`, `firstname`, `lastname`, `email` FROM `users` WHERE `id` = ?', $last_post['userid']);
			$last_post_user = $last_post_user[0]; // first row because we are only getting 1 row
			echo '<tr><td class="forum-icon"';
			if ($last_post['created'] > time() - 259200) { // 3 days
				//echo ' style="opacity: 1"';
				
			} else if ($last_post['created'] > time() - 604800) { // 7 days
				echo ' style="opacity: 0.75;filter: alpha(opacity=75);"';
			} else {
				echo ' style="opacity: 0.5;filter: alpha(opacity=50)"';
			}
			echo '><i class="icon-chat-bubble-two"></i></td><td><a href="/User/Forum/Action/ViewForum/ID/' . urlencode($forum['id']) . '/" class="forum-links">' . $forum['name'] . '</a><br>' . $forum['desc'] . '</td><td>' . $topics_count . '</td><td>' . $posts_count . '</td><td>';
			if (empty($last_post_user)) {
				echo 'None';
			} else {
				echo '<div class="pull-left" style="padding-right:5px"><img src="' . $billic->avatar($last_post_user['email']) . '" style="width:45px;height:45px"></div>';
				echo '<a href="/User/Forum/Action/ViewTopic/ID/' . $last_post_topic['id'] . '/" class="forum-links">' . $last_post_topic['subject'] . '</a><br>By <a href="/User/Forum/Action/ViewProfile/ID/' . $last_post_user['id'] . '/" class="forum-links">' . $last_post_user['firstname'] . ' ' . $last_post_user['lastname'] . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}
	// Create a Forum
	function admin_create_forum() {
		global $billic, $db;
		if (isset($_POST['create'])) {
			if (empty($billic->errors)) {
				$db->insert('forum_forums', array(
					'name' => $_POST['name'],
					'desc' => $_POST['desc'],
				));
				$billic->status = 'created';
			}
		}
		$billic->show_errors();
		echo '<form method="POST"><table class="table table-striped">';
		echo '<tr><td>Forum Name</td><td><input type="text" name="name" class="form-control" value="' . safe($_POST['name']) . '"></td></tr>';
		echo '<tr><td>Description</td><td><textarea name="desc" class="form-control" rows="3"value="' . safe($_POST['desc']) . '"></textarea></td></tr>';
		echo '<tr><td colspan="2" align="center"><input type="submit" name="create" class="btn btn-success" value="Create &raquo;"></td></tr>';
		echo '</table></form>';
	}
	// This section will be accessible at http://yourdomain.com/User/MyModule
	function admin_area() {
		global $billic, $db;
		if ($_GET['Action'] == 'CreateForum') {
			$this->admin_create_forum();
			return;
		}
		echo '<a href="/Admin/Forum/Action/CreateForum/"><button type="button" class="btn btn-success">Create a new Forum</button></a>';
		$forum_forums = $db->q("SELECT * FROM `forum_forums`");
		echo '<table class="table table-bordered"><tr><th>Forum ID</th><th>Forum</th><th>Actions</th></tr>';
		if (count($forum_forums) == 0) {
			echo '<tr><td colspan="3">There are no forums to manage.</td></tr>';
		}
		foreach ($forum_forums as $forum) {
			echo '<tr><td>' . $forum['id'] . '</td><td>' . $forum['name'] . ' <!-- Single button -->
<div class="btn-group">
  <button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
    Action <span class="caret"></span>
  </button>
  <ul class="dropdown-menu">
    <li><a href="#">Action</a></li>
    <li><a href="#">Another action</a></li>
    <li><a href="#">Something else here</a></li>
    <li role="separator" class="divider"></li>
    <li><a href="#">Separated link</a></li>
  </ul>
</div> </td><td><a href=""><button type="button" class="btn btn-success btn-xs"><div class="icon icon-pencil"></div> edit</button></a><a href=""><button type="button" class="btn btn-danger btn-xs"><div class="icon icon-minus-circle"> Delete</button></div></a></td>';
		}
		echo '</table>';
	}
	function settings($array) {
		global $billic, $db;
		if (empty($_POST['update'])) {
			echo '<form method="POST"><table class="table table-striped"><input type="hidden" name="billic_ajax_module" value="Forum">';
			echo '<tr><th>Setting</th><th>Value</th></tr>';
			echo '<tr><td>Forum Name</td><td><input type="text" name="forum_name" class="form-control" value="' . safe(get_config('forum_name')) . '"></td></tr>';
			echo '<tr><td colspan="2" align="center"><input type="submit" name="update" value="Update &raquo;"></td></tr>';
			echo '</table></form>';
		} else {
			if (empty($billic->errors)) {
				set_config('forum_name', $_POST['forum_name']);
				$billic->status = 'updated';
			}
		}
	}
	function textWrap($text) {
		$new_text = '';
		$text_1 = explode('>', $text);
		$sizeof = sizeof($text_1);
		for ($i = 0;$i < $sizeof;++$i) {
			$text_2 = explode('<', $text_1[$i]);
			if (!empty($text_2[0])) {
				$new_text.= preg_replace('#([^\n\r .]{25})#i', '\\1  ', $text_2[0]);
			}
			if (!empty($text_2[1])) {
				$new_text.= '<' . $text_2[1] . '>';
			}
		}
		return $new_text;
	}
}
