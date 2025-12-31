<?php
require_once 'auth_utils.php';

$auth = requireAuth('page');
$currentUser = $auth['user'];
$conn = $auth['conn'];
$isAdmin = isAdmin($currentUser);
$userList = [];
$userResult = pg_query($conn, 'SELECT userid, userlogin FROM users ORDER BY userlogin');
if ($userResult) {
	while ($userRow = pg_fetch_assoc($userResult)) {
		$userList[] = [
			'userid' => (int)$userRow['userid'],
			'userlogin' => $userRow['userlogin']
		];
	}
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta http-equiv="Cache-control" content="no-cache">
	<title>База данных</title>

	<link href="style.css" media="all" rel="Stylesheet" type="text/css" />
	<link href="jquery-ui.css" media="all" rel="Stylesheet" type="text/css">

	<script src="jquery.min.js" type="text/javascript"></script>
	<script src="jquery.maskedinput.js" type="text/javascript"></script>
	<script src="jquery-ui.js" type="text/javascript"></script>
	<script>
		window.availableUsers = <?php echo json_encode($userList, JSON_UNESCAPED_UNICODE); ?>;
		window.currentUserId = <?php echo (int)$currentUser['userid']; ?>;
	</script>
</head>
<body>
	<script>
	$(document).ready(function() {
		let page = 1;
		let params = (new URL(document.location)).searchParams;
		let site = params.get('search');
		let phone = params.get('phone');
		let viewMode = params.get('mode') || 'dates';
		let sitePage = parseInt(params.get('site_page') || '1', 10);
		if (site) {
			document.getElementById("site").value = site;
		}
		if (phone) {
			document.getElementById("phone").value = phone;
		}
		if (!Number.isNaN(sitePage)) {
			sitePage = Math.max(sitePage, 1);
		} else {
			sitePage = 1;
		}
		updateModeButtons(viewMode);
		refreshNotificationBell();
		loadNotifications();
		populateAssignmentMenus();
		scrollToHighlight();

		$("#phone").mask("9 (999) 999-99-99");
		$( function() {
			$("#datepicker").datepicker({
				dateFormat: "dd M yy",
				firstDay: 1,
				dayNamesMin: [ "Вс", "Пн", "Вт", "Ср", "Чт", "Пт", "Сб" ],
				monthNames: [ "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль","Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь" ],
				beforeShow: function(input, inst) {
					setTimeout(function() {
						let $input = $(input);
						let dp = inst.dpDiv;
						let inputOffset = $input.offset();
						let dpWidth = dp.outerWidth();
						let dpHeight = dp.outerHeight();
						let viewportWidth = $(window).width();
						let viewportHeight = $(window).height();
						let scrollTop = $(window).scrollTop();

						let left = inputOffset.left;
						let top = inputOffset.top + $input.outerHeight();
						const gutter = 8;

						if (left + dpWidth > viewportWidth - gutter) {
							left = Math.max(gutter, viewportWidth - dpWidth - gutter);
						}

						if (top + dpHeight > scrollTop + viewportHeight - gutter) {
							top = inputOffset.top - dpHeight - gutter;
						}

						dp.css({ top: top + "px", left: left + "px" });
					}, 0);
				}
			});
			$("#datepicker").datepicker( $.datepicker.regional[ "ru" ] )
		} );
		$("#btn").click(
			function(){
				let site = document.getElementById("site").value;
				sendAjaxForm('sendForm', 'send_query.php', site, page);
				return false; 
			}
		);
		$("#find-phone-button").click(
			function(){
				let phone = document.getElementById("phone").value;
				let state = getControlState();
				loadData({
					phone: phone,
					page: 1,
					mode: state.mode,
					sitePage: 1
				});
				return false; 
			}
		);
		$("#find-site-button").click(
			function(){
				let search = document.getElementById("site").value;
				let state = getControlState();
				loadData({
					search: search,
					page: 1,
					mode: state.mode,
					sitePage: 1
				});
				return false; 
			}
		);
		$('body').on('click', '.pagination li a', function(){
			let params = getUrlState();
			let targetPage = $(this).attr('p');
			if (!targetPage) {
				return;
			}
			loadData({
				search: params.search,
				phone: params.phone,
				page: parseInt(targetPage, 10),
				mode: params.mode,
				sitePage: params.sitePage,
				highlightId: params.highlightId
			});
		});
		$('body').on('click', '.site-nav', function(){
			let params = getUrlState();
			let targetPage = $(this).data('page');
			if (!targetPage) {
				return;
			}
			loadData({
				search: params.search,
				phone: params.phone,
				page: 1,
				mode: 'history',
				sitePage: parseInt(targetPage, 10),
				highlightId: params.highlightId
			});
		});
		$('body').on('click', 'table .call', function(){
			let value = $(this).attr('value');
			let params = (new URL(document.location)).searchParams;
			let site = params.get('search');
			let page = params.get('page');
			let mode = params.get('mode') || 'dates';
			let sitePage = params.get('site_page') || '1';
			if (site == null) {
				site = '';
			}
			if (page == null) {
				page = 1;
			}
			$.post(
				'delete_calldate.php',
				{
					id: value
				},
			);

			loadData({
				search: site,
				page: page,
				mode: mode,
				sitePage: sitePage,
				highlightId: getUrlState().highlightId
			});
		});
		$('body').on('click', 'table .comment', function(){
			let row = $(this).closest('tr');
			let comment = row.data('comment') || '';
			let editable = Number(row.data('editable')) === 1;
			let recordId = row.data('id');
			openEditDialog(comment, editable, recordId);
		});
		$(document).on('contextmenu', '#tbody tr', function(event){
			event.preventDefault();
			let row = $(this);
			let phone = row.data('phone') || '';
			let siteValue = row.data('site') || '';
			let commentValue = row.data('comment') || '';
			let callDate = row.find('.call').text().trim();

			$('#phone').val(phone);
			$('#site').val(siteValue);
			$('#comment').val(commentValue);
			$('#datepicker').val(callDate);
			$('#edit_id').val('');
			$('#btn').val('Записать');
			return false;
		});
		$('#import-button').on('click', function() {
			$('#import-message').empty();
			$('#importForm')[0].reset();
			$('#import-dialog').dialog('open');
		});
		const editModal = document.getElementById('edit-modal');
		if (editModal) {
			const closeEditModal = () => {
				editModal.classList.remove('active');
				editModal.setAttribute('aria-hidden', 'true');
			};
			const openEditModal = () => {
				editModal.classList.add('active');
				editModal.setAttribute('aria-hidden', 'false');
			};

			window.closeEditModal = closeEditModal;
			window.openEditModal = openEditModal;

			editModal.addEventListener('click', (event) => {
				if (event.target === editModal) {
					closeEditModal();
				}
			});
			editModal.querySelectorAll('[data-modal-close]').forEach((button) => {
				button.addEventListener('click', closeEditModal);
			});
		}

		$('#edit-save-button').on('click', function() {
			sendEditForm();
		});
		$('#import-dialog').dialog({
			autoOpen: false,
			modal: true,
			width: 520,
			buttons: [
				{
					text: 'Импортировать',
					id: 'import-save-button',
					click: function() {
						sendImportForm();
					}
				},
				{
					text: 'Закрыть',
					click: function() {
						$(this).dialog('close');
					}
				}
			]
		});
		$('.mode-button').on('click', function(){
			let mode = $(this).data('mode');
			let state = getControlState();
			updateModeButtons(mode);
			loadData({
				search: $("#site").val(),
				phone: $("#phone").val(),
				page: 1,
				mode: mode,
				sitePage: 1
			});
		});
		$('body').on('click', '.row-actions-toggle', function(event) {
			event.stopPropagation();
			let menu = $(this).siblings('.row-actions-menu');
			$('.row-actions-menu').not(menu).removeClass('active');
			menu.toggleClass('active');
			populateAssignmentMenus(menu);
		});
		$('body').on('click', '.row-actions-menu', function(event) {
			event.stopPropagation();
		});
		$('body').on('click', '.row-actions-send', function(event) {
			event.stopPropagation();
			let container = $(this).closest('.row-actions');
			let callId = container.data('call-id');
			let selectedUser = container.find('.row-actions-select').val();
			let status = container.find('.row-actions-status');
			if (!selectedUser) {
				status.text('Выберите пользователя.');
				return;
			}
			status.text('Отправляем...');
			$.post('assign_call.php', { call_id: callId, assigned_to: selectedUser }, function(response) {
				if (response.success) {
					status.text('Передано.');
					refreshNotificationBell();
				} else {
					status.text(response.message || 'Ошибка.');
				}
			}, 'json').fail(function() {
				status.text('Ошибка отправки.');
			});
		});
		$('body').on('click', '.row-actions-copy', function(event) {
			event.stopPropagation();
			let container = $(this).closest('.row-actions');
			let callId = container.data('call-id');
			let link = window.location.origin + '/main.php?highlight_id=' + callId;
			copyToClipboard(link);
			container.find('.row-actions-status').text('Ссылка скопирована.');
		});
		$(document).on('click', function() {
			$('.row-actions-menu').removeClass('active');
		});
		$('body').on('click', '#notification-button', function(event) {
			event.stopPropagation();
			$('#notification-panel').toggleClass('active');
			loadNotifications();
		});
		$('body').on('click', '#notification-panel', function(event) {
			event.stopPropagation();
		});
		$('body').on('click', '#notifications-mark-read', function() {
			$.post('notifications.php', { action: 'mark_read' }, function(response) {
				if (response.success) {
					loadNotifications();
					refreshNotificationBell();
				}
			}, 'json');
		});
		$(document).on('click', function() {
			$('#notification-panel').removeClass('active');
		});
	});

	function loadData(options) {
		let query = new URLSearchParams();
		let search = options.search || '';
		let phone = options.phone || '';
		let page = options.page || 1;
		let mode = options.mode || 'dates';
		let sitePage = options.sitePage || 1;
		let highlightId = options.highlightId || '';

		if (search) {
			query.set('search', search);
		}
		if (phone) {
			query.set('phone', phone);
		}
		if (page > 1) {
			query.set('page', page);
		}
		query.set('mode', mode);
		if (sitePage > 1) {
			query.set('site_page', sitePage);
		}
		if (highlightId) {
			query.set('highlight_id', highlightId);
		}

		let url = "/main.php";
		if (query.toString()) {
			url += "?" + query.toString();
		}

		window.history.pushState({}, document.title, url);

		$.get(
			"show_base.php",
			{
				search: search,
				phone: phone,
				page: page,
				mode: mode,
				site_page: sitePage,
				highlight_id: highlightId
			},
			onAjaxSuccess
		);

		function onAjaxSuccess(data) {
			$('#data').html(data);
			resetEditState();
			populateAssignmentMenus();
			scrollToHighlight();
		}
	};

	function resetEditState() {
		document.getElementById("comment").value = "";
		$("#edit_id").val("");
		$("#btn").val("Записать");
	}

	function updateModeButtons(mode) {
		$('.mode-button').removeClass('active');
		$('.mode-button[data-mode="' + mode + '"]').addClass('active');
	}

	function getControlState() {
		return {
			mode: $('.mode-button.active').data('mode') || 'dates'
		};
	}

	function getUrlState() {
		let params = (new URL(document.location)).searchParams;
		return {
			search: params.get('search') || '',
			phone: params.get('phone') || '',
			page: parseInt(params.get('page') || '1', 10),
			mode: params.get('mode') || 'dates',
			sitePage: parseInt(params.get('site_page') || '1', 10),
			highlightId: params.get('highlight_id') || ''
		};
	}

	function populateAssignmentMenus(container) {
		let $menus = container ? $(container).find('.row-actions-select') : $('.row-actions-select');
		$menus.each(function() {
			let $select = $(this);
			if ($select.data('populated')) {
				return;
			}
			$select.append($('<option>', { value: '', text: 'Выберите пользователя' }));
			(window.availableUsers || []).forEach(function(user) {
				if (user.userid === window.currentUserId) {
					return;
				}
				$select.append($('<option>', { value: user.userid, text: user.userlogin }));
			});
			$select.data('populated', true);
		});
	}

	function copyToClipboard(text) {
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text);
			return;
		}
		let temp = document.createElement('textarea');
		temp.value = text;
		document.body.appendChild(temp);
		temp.select();
		document.execCommand('copy');
		document.body.removeChild(temp);
	}

	function scrollToHighlight() {
		let params = new URLSearchParams(window.location.search);
		let highlightId = params.get('highlight_id');
		if (!highlightId) {
			return;
		}
		let target = document.getElementById('call-row-' + highlightId);
		if (target) {
			target.classList.add('highlighted-row');
			target.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}

	function refreshNotificationBell() {
		$.get('notifications.php', function(response) {
			if (response && typeof response.unread_count !== 'undefined') {
				let hasUnread = response.unread_count > 0;
				$('#notification-dot').toggleClass('active', hasUnread);
			}
		}, 'json');
	}

	function loadNotifications() {
		$.get('notifications.php', function(response) {
			if (!response || !response.notifications) {
				return;
			}
			let list = $('#notification-list');
			list.empty();
			if (response.notifications.length === 0) {
				list.append('<div class="notification-empty">Нет новых уведомлений.</div>');
			} else {
				response.notifications.forEach(function(item) {
					let callDate = item.nextcalldate ? item.nextcalldate : 'Без даты';
					let assignedAt = item.assigned_at || '';
					let from = item.assigned_by || 'Неизвестно';
					let unreadClass = item.read_at ? '' : 'unread';
					let html = '<div class="notification-item ' + unreadClass + '">' +
						'<div class="notification-title">Назначен звонок - ' + callDate + '</div>' +
						'<div class="notification-meta">' + assignedAt + ' • от: ' + from + '</div>' +
						'</div>';
					list.append(html);
				});
			}
			let hasUnread = response.unread_count > 0;
			$('#notification-dot').toggleClass('active', hasUnread);
		}, 'json');
	}
 
	function sendAjaxForm(ajax_form, url, site, page) {
    // Блокируем кнопку на время запроса
    $("#btn").prop("disabled", true).val("Ждите...");

    $.ajax({
        url: url,
        type: "POST",
        dataType: "json", // Явно говорим jQuery ждать JSON
        data: $("#" + ajax_form).serialize(),
        success: function(response) {
            // Разблокируем кнопку
            $("#btn").prop("disabled", false).val("Записать");

            // jQuery уже распарсил JSON, response - это объект
            var result = response;

            if (result.success) {
                // Если успех
                let params = getUrlState();
				loadData({
					search: params.search,
					phone: params.phone,
					page: params.page,
					mode: params.mode,
					sitePage: params.sitePage
				}); // Обновляем таблицу
                
                // Очищаем поля формы
                $('#sendForm')[0].reset();
				resetEditState();
                
                // Показываем сообщение об успехе
                $('#result_form').html('<span style="color:green;">' + result.message + '</span>');
                setTimeout(function() { $('#result_form').empty(); }, 3000);
            } else {
                // Если ошибка логики (например, пустые поля)
                alert("Ошибка: " + result.message);
            }
        },
        error: function(xhr, status, error) {
            $("#btn").prop("disabled", false).val("Записать");
            console.error("Ошибка сервера:", xhr.responseText);
            alert("Не удалось сохранить данные. Проверьте консоль (F12).");
        }
    });
}

	function openEditDialog(comment, editable, recordId) {
		$('#edit-comment').val(comment);
		$('#edit_id_dialog').val(recordId || '');
		$('#edit-message').empty();

		if (!editable) {
			$('#edit-message').html('<span style="color:#dc3545;">Комментарий можно редактировать только в течение 1 часа после добавления.</span>');
			$('#edit-comment').prop('disabled', true);
			$('#edit-save-button').prop('disabled', true);
		} else {
			$('#edit-comment').prop('disabled', false);
			$('#edit-save-button').prop('disabled', false);
		}

		if (typeof window.openEditModal === 'function') {
			window.openEditModal();
		}
	}

	function sendEditForm() {
		if (!$('#edit_id_dialog').val()) {
			$('#edit-message').html('<span style="color:#dc3545;">Не удалось определить запись для редактирования.</span>');
			return;
		}

		$('#edit-save-button').prop('disabled', true);
		$.ajax({
			url: 'send_query.php',
			type: 'POST',
			dataType: 'json',
			data: $('#editForm').serialize(),
			success: function(response) {
				if (response.success) {
					let params = getUrlState();
					loadData({
						search: params.search,
						phone: params.phone,
						page: params.page,
						mode: params.mode,
						sitePage: params.sitePage
					});
					if (typeof window.closeEditModal === 'function') {
						window.closeEditModal();
					}
				} else {
					$('#edit-message').html('<span style="color:#dc3545;">' + response.message + '</span>');
				}
			},
			error: function() {
				$('#edit-message').html('<span style="color:#dc3545;">Не удалось сохранить комментарий.</span>');
			},
			complete: function() {
				$('#edit-save-button').prop('disabled', false);
			}
		});
	}

	function sendImportForm() {
		let fileInput = $('#import_file')[0];
		if (!fileInput || !fileInput.files || !fileInput.files.length) {
			$('#import-message').html('<span style="color:#dc3545;">Выберите файл для импорта.</span>');
			return;
		}

		$('#import-save-button').prop('disabled', true);
		let formData = new FormData();
		formData.append('import_file', fileInput.files[0]);

		$.ajax({
			url: 'import.php',
			type: 'POST',
			dataType: 'json',
			data: formData,
			contentType: false,
			processData: false,
			success: function(response) {
				if (response.success) {
					let params = getUrlState();
					loadData({
						search: params.search,
						phone: params.phone,
						page: params.page,
						mode: params.mode,
						sitePage: params.sitePage
					});
					$('#import-message').html('<span style="color:green;">' + response.message + '</span>');
				} else {
					$('#import-message').html('<span style="color:#dc3545;">' + response.message + '</span>');
				}
			},
			error: function() {
				$('#import-message').html('<span style="color:#dc3545;">Не удалось выполнить импорт.</span>');
			},
			complete: function() {
				$('#import-save-button').prop('disabled', false);
			}
		});
	}
	</script>

<div class="container">
	<div class="top-actions">
		<a href="/main.php" class="home-button">На главную</a>
		<div class="top-actions-right">
			<?php if ($isAdmin) { ?>
				<a href="/managment.php" class="home-button">Управление пользователями</a>
			<?php } ?>
			<div class="notification-wrapper">
				<button type="button" id="notification-button" class="notification-button" title="Уведомления">
					<span class="notification-icon">🔔</span>
					<span id="notification-dot" class="notification-dot"></span>
				</button>
				<div id="notification-panel" class="notification-panel">
					<div class="notification-header">
						<span>Уведомления</span>
						<button type="button" id="notifications-mark-read" class="notification-mark-read">Отметить прочитанными</button>
					</div>
					<div id="notification-list" class="notification-list"></div>
				</div>
			</div>
			<button type="button" class="import-button" id="import-button">Импорт</button>
			<form class="exit" method="POST" action="logout.php">
				<input name="submit" type="submit" value="Выйти">
			</form>
		</div>
	</div>
  	<div class="forms">
		<form method="post" id="sendForm" action="">
			<!-- Верхний ряд: Поля ввода -->
			<div class="row first-row">
				<div class="input-column">
					<div class="find-row">
						<!-- Текст теперь внутри поля (placeholder) -->
						<input name="phone" placeholder="Телефон: 1 (234) 567-89-00" id="phone" type="tel">
						<input class="find-button" type="submit" value="" id="find-phone-button" title="Найти по телефону">
					</div>
				</div>
				<div class="input-column">
					<div class="find-row">
						<!-- Текст теперь внутри поля (placeholder) -->
						<input name="site" placeholder="Сайт: example.ru" type="text" id="site">
						<input class="find-button" type="submit" value="" id="find-site-button" title="Найти по сайту">
					</div>
				</div>
				<div class="input-column">
					<!-- Текст теперь внутри поля (placeholder) -->
					<input type="text" id="datepicker" name="date" placeholder="Дата следующего звонка">
				</div>
				<div class="input-column save-button">
					<input type="submit" value="Записать" id="btn">
				</div>
			</div>
			
			<!-- Нижний ряд: Комментарий -->
			<div class="row">
				<div class="input-column" style="width: 100%;">
					<!-- Текст теперь внутри поля (placeholder) -->
					<textarea rows="2" cols="100" name="comment" id="comment" placeholder="Введите комментарий..."></textarea>
				</div>
			</div>
			<input type="hidden" name="edit_id" id="edit_id">
		</form>
		
		<div id="result_form"></div>
	</div>

	<div id="edit-modal" class="modal-overlay" aria-hidden="true">
		<div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="edit-modal-title">
			<div class="modal-header">
				<div class="modal-title" id="edit-modal-title">Редактирование комментария</div>
				<button type="button" class="modal-close" data-modal-close aria-label="Закрыть">×</button>
			</div>
			<div class="dialog-message" id="edit-message"></div>
			<form id="editForm">
				<textarea rows="4" cols="100" name="comment" id="edit-comment" placeholder="Введите новый текст комментария..."></textarea>
				<input type="hidden" name="edit_id" id="edit_id_dialog">
			</form>
			<div class="modal-actions">
				<button type="button" class="modal-secondary" data-modal-close>Закрыть</button>
				<button type="button" class="modal-primary" id="edit-save-button">Сохранить</button>
			</div>
		</div>
	</div>

	<div id="import-dialog" title="Импорт данных" style="display:none;">
		<div class="dialog-message">Формат: телефон; сайт; дата (дд.мм.гггг); комментарий.</div>
		<form id="importForm" enctype="multipart/form-data">
			<input type="file" name="import_file" id="import_file" accept=".txt,.csv">
		</form>
		<div class="import-examples">
			<div>Примеры файлов для импорта:</div>
			<ul>
				<li><a href="/import_example.csv" download>import_example.csv</a></li>
				<li><a href="/import_example.txt" download>import_example.txt</a></li>
			</ul>
		</div>
		<div class="dialog-message" id="import-message"></div>
	</div>

	<div class="view-controls">
		<div class="mode-switch">
			<button type="button" class="mode-button" data-mode="dates">По датам</button>
			<button type="button" class="mode-button" data-mode="history">История комментариев</button>
		</div>
	</div>

	<div id="data">
		<?php include 'show_base.php';?>
	</div>

</body>
</html>
