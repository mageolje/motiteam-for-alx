async function api(path, options = {}) {
  const res = await fetch(path, {
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin',
    ...options,
  });
  if (!res.ok) throw new Error('Request failed');
  return res.json();
}

// Chat polling
let chatTimer = null;
function startChatPolling(partnerId) {
  stopChatPolling();
  chatTimer = setInterval(async () => {
    try {
      const data = await api(`/controllers/chat.php?partner_id=${encodeURIComponent(partnerId)}`);
      const box = document.getElementById('chat-box');
      if (box) {
        box.innerHTML = data.messages.map(m => `<div><strong>${m.from_name}:</strong> ${escapeHtml(m.content)}</div>`).join('');
        box.scrollTop = box.scrollHeight;
      }
    } catch {}
  }, 2500);
}
function stopChatPolling() { if (chatTimer) { clearInterval(chatTimer); chatTimer = null; } }

// Alarm checks
let alarmTimer = null;
function startAlarmChecks() {
  if (Notification && Notification.permission !== 'granted') {
    Notification.requestPermission();
  }
  stopAlarmChecks();
  alarmTimer = setInterval(async () => {
    try {
      const data = await api('/controllers/alarms.php');
      const now = Date.now();
      (data.alarms || []).forEach(a => {
        const t = new Date(a.alarm_time).getTime();
        if (!a.notified && t <= now) {
          notify(`Alarm: ${a.title}`);
          // mark notified
          fetch('/controllers/alarms.php', { method: 'POST', body: new URLSearchParams({ action: 'ack', id: a.id }) });
        }
      })
    } catch {}
  }, 5000);
}
function stopAlarmChecks() { if (alarmTimer) { clearInterval(alarmTimer); alarmTimer = null; } }

function notify(msg) {
  if (Notification && Notification.permission === 'granted') {
    new Notification(msg);
  } else {
    alert(msg);
  }
}

function escapeHtml(str) {
  return str.replace(/[&<>"]/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]));
}

window.MHApp = { startChatPolling, stopChatPolling, startAlarmChecks };