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
</head>
<body>
	<script>
	$(document).ready(function() {
		let page = 1;
		let params = (new URL(document.location)).searchParams;
		let site = params.get('search');
		let phone = params.get('phone');
		let viewMode = params.get('mode') || 'dates';
		let sortOrder = params.get('sort') || 'asc';
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
		$('#sort-order').val(sortOrder);
		updateModeButtons(viewMode);
		toggleSortControl(viewMode);

		$("#phone").mask("9 (999) 999-99-99");
		$( function() {
			$("#datepicker").datepicker({
				dateFormat: "dd M yy",
				firstDay: 1,
				dayNamesMin: [ "Вс", "Пн", "Вт", "Ср", "Чт", "Пт", "Сб" ],
				monthNames: [ "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль","Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь" ]
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
					sort: state.sort,
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
					sort: state.sort,
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
				sort: params.sort,
				sitePage: params.sitePage
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
				sort: params.sort,
				sitePage: parseInt(targetPage, 10)
			});
		});
		$('body').on('click', 'table .call', function(){
			let value = $(this).attr('value');
			let params = (new URL(document.location)).searchParams;
			let site = params.get('search');
			let page = params.get('page');
			let mode = params.get('mode') || 'dates';
			let sort = params.get('sort') || 'asc';
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
				sort: sort,
				sitePage: sitePage
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
		$('#edit-dialog').dialog({
			autoOpen: false,
			modal: true,
			width: 520,
			buttons: [
				{
					text: 'Сохранить',
					id: 'edit-save-button',
					click: function() {
						sendEditForm();
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
			toggleSortControl(mode);
			loadData({
				search: $("#site").val(),
				phone: $("#phone").val(),
				page: 1,
				mode: mode,
				sort: state.sort,
				sitePage: 1
			});
		});
		$('#sort-order').on('change', function(){
			let state = getControlState();
			loadData({
				search: $("#site").val(),
				phone: $("#phone").val(),
				page: 1,
				mode: state.mode,
				sort: state.sort,
				sitePage: 1
			});
		});
	});

	function loadData(options) {
		let query = new URLSearchParams();
		let search = options.search || '';
		let phone = options.phone || '';
		let page = options.page || 1;
		let mode = options.mode || 'dates';
		let sort = options.sort || 'asc';
		let sitePage = options.sitePage || 1;

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
		query.set('sort', sort);
		if (sitePage > 1) {
			query.set('site_page', sitePage);
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
				sort: sort,
				site_page: sitePage
			},
			onAjaxSuccess
		);

		function onAjaxSuccess(data) {
			$('#data').html(data);
			resetEditState();
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

	function toggleSortControl(mode) {
		if (mode === 'dates') {
			$('#sort-control').show();
		} else {
			$('#sort-control').hide();
		}
	}

	function getControlState() {
		return {
			mode: $('.mode-button.active').data('mode') || 'dates',
			sort: $('#sort-order').val() || 'asc'
		};
	}

	function getUrlState() {
		let params = (new URL(document.location)).searchParams;
		return {
			search: params.get('search') || '',
			phone: params.get('phone') || '',
			page: parseInt(params.get('page') || '1', 10),
			mode: params.get('mode') || 'dates',
			sort: params.get('sort') || 'asc',
			sitePage: parseInt(params.get('site_page') || '1', 10)
		};
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
					sort: params.sort,
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

		$('#edit-dialog').dialog('open');
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
						sort: params.sort,
						sitePage: params.sitePage
					});
					$('#edit-dialog').dialog('close');
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
						sort: params.sort,
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

	<div id="edit-dialog" title="Редактирование комментария" style="display:none;">
		<div class="dialog-message" id="edit-message"></div>
		<form id="editForm">
			<textarea rows="4" cols="100" name="comment" id="edit-comment" placeholder="Введите новый текст комментария..."></textarea>
			<input type="hidden" name="edit_id" id="edit_id_dialog">
		</form>
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
		<div class="sort-control" id="sort-control">
			<label for="sort-order">Сортировка:</label>
			<select id="sort-order">
				<option value="asc">Сначала старые</option>
				<option value="desc">Сначала новые</option>
			</select>
		</div>
	</div>

	<div id="data">
		<?php include 'show_base.php';?>
	</div>

</body>
</html>
