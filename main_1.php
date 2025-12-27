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
		if (site) {
			document.getElementById("site").value = site;
		}

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
				phoneSearch(phone, page);
				return false; 
			}
		);
		$("#find-site-button").click(
			function(){
				let search = document.getElementById("site").value;
				mode(search, page);
				return false; 
			}
		);
		$('body').on('click', ' .pagination li.active', function(){
			let params = (new URL(document.location)).searchParams;
			let site = params.get('search');
			let phone = params.get('phone');
			var page = $(this).attr('p');
			if (phone != null) {
				phoneSearch(phone, page);
			} else if (site == null) {
				site = '';
				mode(site, page);
			} else {
				mode(site, page);
			}
		});
		$('body').on('click', 'table .call', function(){
			let value = $(this).attr('value');
			let params = (new URL(document.location)).searchParams;
			let site = params.get('search');
			let page = params.get('page');
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

			mode(site, page);
		});
	});

	function mode(search, page) {
		if (search) {
			window.history.pushState({}, document.title, "/main.php?search=" + search + "&page=" + page );
		} else {
			window.history.pushState({}, document.title, "/main.php?page=" + page );
		}

		$.get(
			"show_base.php",
			{
				search: search,
				page: page
			},
			onAjaxSuccess
		);

		function onAjaxSuccess(data) {
			$('#data').html(data);
			document.getElementById("comment").value = "";
		}

	};

	function phoneSearch(phone, page) {
		if (phone) {
			window.history.pushState({}, document.title, "/main.php?phone=" + phone + "&page=" + page );
		} else {
			window.history.pushState({}, document.title, "/main.php?page=" + page );
		}

		$.get(
			"show_base.php",
			{
				phone: phone,
				page: page
			},
			onAjaxSuccess
		);

		function onAjaxSuccess(data) {
			$('#data').html(data);
			document.getElementById("comment").value = "";
		}

	};
 
	function sendAjaxForm(ajax_form, url, site, page) {
		$.ajax({
			url:     url,
			type:     "POST",
			dataType: "html",
			data: $("#"+ajax_form).serialize(),
			success: function(response) {
				result = $.parseJSON(response);
				mode(site, page);
			},
			error: function(response) {
				$('#result_form').html('Ошибка. Данные не отправлены.');
			}
		});
	}
	</script>
<div class="container">
  	<div class="forms">
		<form method="post" id="sendForm" action="">
			<div class="row">
				<div class="input-column">
					<p>Телефон:</p>
					<div class="find-row">
						<input name="phone" placeholder="1 (234) 567-89-00" id="phone" type="tel">
						<input class="find-button" type="submit" value="" id="find-phone-button">
					</div>
				</div>
				<div class="input-column">
					<p>Сайт:</p>
					<div class="find-row">
						<input name="site" placeholder="example.ru" type="text" id="site">
						<input class="find-button" type="submit" value="" id="find-site-button">
					</div>
				</div>
				<div class="input-column">
					<p>Дата следующего звонка:</p>
					<input type="text" id="datepicker" name="date">
				</div>
				<div class="input-column save-button">
					<input type="submit" value="Записать" id="btn">
				</div>
			</div>
			<div class="row">
				<div class="input-column">
					<p>Комментарий:</p>
					<textarea rows="2" cols="100" name="comment" id="comment"></textarea>
				</div>
			</div>
	
		</form>

		<form class="exit" method="POST" action="logout.php">
			<input name="submit" type="submit" value="Выйти">
		</form>

		<!-- ===== НАЧАЛО ИЗМЕНЕНИЯ ===== -->
		<a href="/main.php" class="home-button">На главную</a>
		<!-- ===== КОНЕЦ ИЗМЕНЕНИЯ ===== -->
		
		<div id="result_form"></div>
	</div>

	<div id="data">
		<?php include 'show_base.php';?>
	</div>

</body>
</html>
