(function () {
	'use strict';

	if (typeof window.sforgeAdmin === 'undefined') return;

	var $log    = document.querySelector('.sforge-log');
	var $status = document.querySelector('.sforge-status');
	if (!$log) return;

	var POLL_MS = 4000;
	var levelClass = function (lvl) {
		return 'sforge-level-' + (lvl || 'info').replace(/[^a-z]/gi, '');
	};

	var escape = function (s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	};

	var renderTable = function (entries) {
		if (!entries || !entries.length) {
			return '<p><em>No activity yet.</em></p>';
		}
		var html = '<table class="widefat striped">'
			+ '<thead><tr><th class="sforge-col-time">Time</th><th class="sforge-col-level">Level</th><th>Message</th></tr></thead>'
			+ '<tbody>';
		for (var i = 0; i < entries.length; i++) {
			var e = entries[i];
			html += '<tr class="sforge-row ' + levelClass(e.level) + '">'
				+  '<td>' + escape(e.time)  + '</td>'
				+  '<td>' + escape(e.level) + '</td>'
				+  '<td>' + (e.msg || '')   + '</td>'  // msg is server-sanitized via wp_kses_post
				+  '</tr>';
		}
		html += '</tbody></table>';
		return html;
	};

	var setStatus = function (state, hint) {
		if (!$status) return;
		$status.className = 'sforge-status sforge-status-' + state;
		$status.innerHTML = (state === 'running'
			? '<span class="sforge-spinner"></span><span>Working&hellip; ' + escape(hint || '') + '</span>'
			: state === 'queued'
				? '<span class="sforge-dot"></span><span>Deploy queued ' + escape(hint || '') + '</span>'
				: '<span class="sforge-dot sforge-dot-idle"></span><span>Idle</span>'
		);
	};

	var lastTopTime = null;
	var fetchLog = function () {
		var url = sforgeAdmin.ajaxurl + '?action=sforge_get_log&nonce=' + encodeURIComponent(sforgeAdmin.nonce);
		fetch(url, { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (resp) {
				if (!resp || !resp.success) return;
				var data = resp.data || {};
				var entries = data.log || [];
				var topTime = entries.length ? entries[0].time : null;
				if (topTime !== lastTopTime) {
					$log.innerHTML = renderTable(entries);
					lastTopTime = topTime;
				}
				if (data.running) {
					var hint = entries.length ? '— ' + (entries[0].msg || '').replace(/<[^>]+>/g, '') : '';
					setStatus('running', hint);
				} else if (data.next != null && data.next > 0) {
					setStatus('queued', 'in ' + data.next + 's');
				} else {
					setStatus('idle');
				}
			})
			.catch(function () { /* swallow */ });
	};

	fetchLog();
	setInterval(fetchLog, POLL_MS);
})();
