<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth_check.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/db_connect.php';

$is_readonly = isset($_GET['readonly']) && $_GET['readonly'] === 'true';
$page_title = "Parking Map — Blueprint";

include '../templates/header.php';

?>

<link rel="stylesheet" href="/static/css/content.css">
  <style>
    :root {
      --slot-green: #bff0bb;
      --slot-red: #f7c7c7;
      --slot-yellow: #fff0a2;
      --slot-border: #37a448;
      --lane-bg: #cfd5d9;
      --bg: #e9eef1;
      --font: 'Inter', Arial, sans-serif;
    }

   main {
  font-family: var(--font);
  background: var(--bg);
  padding: 28px;
  margin: 0 auto;
  -webkit-font-smoothing: antialiased;
  border-radius: 12px;
  max-width: 1200px;
}


    h2 {
      text-align: center;
      margin-bottom: 14px;
      color: #222;
      letter-spacing: 0.3px;
      font-weight: 700;
    }

    .controls {
      text-align: center;
      margin-bottom: 20px;
    }

    select,
    button {
      padding: 10px 14px;
      font-size: 15px;
      border-radius: 6px;
      border: 1px solid #bbb;
      outline: none;
      font-family: var(--font);
    }

    button {
      background: #0078d4;
      color: white;
      cursor: pointer;
      border: none;
      transition: 0.2s ease;
      font-weight: 600;
    }

    button:hover {
      background: #005fa3;
    }

    .map-wrapper {
      width: 1000px;
      max-width: 95%;
      margin: 0 auto;
      background: #fff;
      border-radius: 14px;
      padding: 32px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }

    .map-scale-container {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100%;
      overflow-x: auto;
    }
    
    .lot-area {
      background: linear-gradient(180deg, #eef2f4, #f6f8f9);
      border-radius: 10px;
      padding: 28px;
      box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    /* === SLOT DESIGN === */
    .slot {
      border-radius: 10px;
      background: var(--slot-green);
      border: 2px solid var(--slot-border);
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.07);
      cursor: pointer;
      transition: transform .18s ease, box-shadow .18s ease, background 0.25s;
    }

    .slot:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 18px rgba(0, 0, 0, 0.15);
    }

    .slot.occupied {
      background: var(--slot-red);
      border-color: #b24b4b;
    }

    .slot.reserved {
      background: var(--slot-yellow);
      border-color: #b38700;
    }

    .slot.unavailable {
      background: #efefef;
      border-color: #bdbdbd;
    }

    .slot .code {
      position: absolute;
      bottom: 8px;
      font-weight: 700;
      color: #111;
      font-size: 0.8em;
      pointer-events: none;
    }

    .slot .meta {
      position: absolute;
      top: 8px;
      left: 8px;
      font-size: 0.7em;
      color: #333;
      opacity: .9;
      pointer-events: none;
    }

    .slot img.car {
      filter: drop-shadow(0 3px 4px rgba(0, 0, 0, 0.2));
    }

    /* === LANES === */
    .lane {
      width: 80%;
      height: 60px;
      background: var(--lane-bg);
      border-radius: 8px;
      box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2vw;
      color: #333;
      margin: 28px auto;
      font-weight: 600;
    }

    /* === SLOT LAYOUT === */
    .top-row {
      display: flex;
      justify-content: center;
      gap: 22px;
      margin-bottom: 25px;
    }

    .top-row .slot {
      width: 88px;
      height: 150px;
    }

    .middle-container {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin: 25px 0;
      position: relative;
    }

    .left-section,
    .middle-section,
    .right-section {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
    }

    .middle-section {
      margin: 0 60px;
    }

    .left-section .slot,
    .middle-section .slot,
    .right-section .slot {
      width: 150px;
      height: 90px;
    }

    .up-down-lane {
      width: 60px;
      height: 100%;
      background: var(--lane-bg);
      border-radius: 10px;
      box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2vw;
    }

    .middle-container .up-down-lane:nth-of-type(2) {
      position: absolute;
      left: 25%;
      top: 0;
      bottom: 0;
      margin: auto;
    }

    .middle-container .up-down-lane:nth-of-type(4) {
      position: absolute;
      left: 68%;
      top: 0;
      bottom: 0;
      margin: auto;
    }

    .bottom-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 30px;
      padding: 0 150px;
    }

    .bottom-left,
    .bottom-right {
      display: flex;
      gap: 20px;
    }

    .bottom-row .slot {
      width: 88px;
      height: 150px;
    }

        #message {
          text-align: center;
          margin-top: 14px;
          font-weight: 600;
          transition: 0.3s;
        }
        
        @media (max-width: 1200px) {
      .map-wrapper {
        transform: scale(0.9);
      }
    }
    
    @media (max-width: 1000px) {
      .map-wrapper {
        transform: scale(0.8);
      }
    }
    
    @media (max-width: 800px) {
      .map-wrapper {
        transform: scale(0.7);
      }
    }
    
    @media (max-width: 650px) {
      .map-wrapper {
        transform: scale(0.6);
      }
    }
    
    @media (max-width: 500px) {
      .map-wrapper {
        transform: scale(0.5);
      }
    }
    
    @media (max-width: 400px) {
      .map-wrapper {
        transform: scale(0.45);
      }
    }
    
    .map-wrapper {
      image-rendering: -webkit-optimize-contrast;
      transform-origin: top center;
      transition: transform 0.3s ease;
    }
    
    /* Prevent Bootstrap container from shrinking the map */
main.container {
  max-width: none;
  padding: 0;
  margin: 0;
  background: var(--bg);
}

/* Optional: add your own padding */
.map-scale-container {
  padding: 20px 0;
}


  </style>

<?php
// ✅ Step 4: Now include sidebar (appears after header)
include '../templates/sidebar.php';

// ✅ Step 5: Your PHP logic
$can_assign = ($_SESSION['role'] ?? '') === 'employee';
$preselect_client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
?>

  <h2>Parking Slot Map</h2>

  <div class="controls" style="text-align:center;">
  <?php if (!$is_readonly): ?>
    <label>Client: </label>
    <select id="client_select" <?php echo $can_assign ? '' : 'disabled' ?>>
      <option value="">-- Select client --</option>
    </select>
    <button id="refreshBtn">Refresh</button>
  <?php else: ?>
    <button id="refreshBtn">Refresh</button>
  <?php endif; ?>
</div>


<div class="map-scale-container">
  <div class="map-wrapper">
    <div class="lot-area">

      <!-- TOP ROW -->
      <div class="top-row" id="topRow"></div>

      <div class="lane">⬅️ ➡️</div>

      <!-- MIDDLE SECTION -->
      <div class="middle-container">
        <div class="left-section" id="leftCol"></div>

        <div class="up-down-lane">⬆️⬇️</div>

        <div class="middle-section" id="centerGrid"></div>

        <div class="up-down-lane">⬆️⬇️</div>

        <div class="right-section" id="rightCol"></div>
      </div>

      <div class="lane">⬅️ ➡️</div>

      <!-- BOTTOM ROW -->
      <div class="bottom-row">
        <div class="bottom-left" id="bottomLeft"></div>
        <div class="bottom-right" id="bottomRight"></div>
      </div>

    </div>
  </div>
</div>
  <div id="message"></div>

  <script>
    const layoutOrder = {
      top: ['P1', 'P2', 'P3', 'P4'],
      left: ['P5', 'P6', 'P7', 'P8'],
      center: ['P9', 'P10', 'P11', 'P12'],
      right: ['P13', 'P14', 'P15', 'P16'],
      bottomLeft: ['P17', 'P18'],
      bottomRight: ['P19', 'P20']
    };

    const POLL_INTERVAL = 5000;
    let pollTimer = null;
    const preselectClientId = <?= json_encode($preselect_client_id) ?>;
    const canAssign = <?= $can_assign ? 'true' : 'false' ?>;

    function showMessage(txt, isError = false) {
      const el = document.getElementById('message');
      el.textContent = txt;
      el.style.color = isError ? 'crimson' : '#2b7a2b';
      if (!txt) el.textContent = '';
    }

    async function loadData(preselect = preselectClientId) {
      try {
        const [slotsResp, clientsResp] = await Promise.all([
          fetch('get_slots.php', { cache: 'no-store' }),
          fetch('get_clients_for_assign.php', { cache: 'no-store' })
        ]);
        if (!slotsResp.ok || !clientsResp.ok) throw new Error('Fetch failed');
        const slots = await slotsResp.json();
        const clients = await clientsResp.json();
        renderClients(clients, preselect);
        renderSlots(slots);
      } catch (err) {
        console.error(err);
        showMessage('Failed to load data', true);
      }
    }

    function renderClients(clients, selectedId = null) {
      const sel = document.getElementById('client_select');
      sel.innerHTML = '<option value="">-- Select client --</option>';
      clients.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = `${c.name} — ${c.vehicle_plate || 'no plate'} (${c.parking_type})`;
        sel.appendChild(opt);
      });
      if (selectedId) sel.value = selectedId;
    }

    function makeSlotElement(slot) {
      const div = document.createElement('div');
      div.className = 'slot ' + (slot.status || 'vacant');
      div.dataset.slotId = slot.id;
      div.dataset.slotCode = slot.slot_code || '';

      let positionGroup = 'center';
      for (const [group, codes] of Object.entries(layoutOrder)) {
        if (codes.includes(slot.slot_code)) {
          positionGroup = group;
          break;
        }
      }

      const carPositions = {
        top: { top: '4px', left: '-7px', rotate: '180deg' },
        bottom: { bottom: '4px', left: '8px', rotate: '0deg' },
        left: { top: '-30px', left: '25px', rotate: '90deg' },
        right: { top: '-30px', right: '25px', rotate: '-90deg' },
        center: { top: '-30px', left: '25px', rotate: '90deg' },
        bottomLeft: { bottom: '3px', left: '-7px', rotate: '0deg' },
        bottomRight: { bottom: '3px', right: '-7px', rotate: '0deg' }
      };

      if (slot.status === 'occupied' || slot.status === 'reserved') {
        const img = document.createElement('img');
        img.className = 'car';
        img.alt = slot.status === 'reserved' ? 'yellow car' : 'red car';
        img.src = slot.status === 'reserved'
          ? '/static/images/yellow_car.png'
          : '/static/images/red_car.png';
        img.style.width = '100px'
        img.style.position = 'absolute';
        img.style.pointerEvents = 'none';
        img.style.transition = 'transform .3s ease';
        const pos = carPositions[positionGroup] || carPositions.center;
        img.style.top = pos.top || '';
        img.style.bottom = pos.bottom || '';
        img.style.left = pos.left || '';
        img.style.right = pos.right || '';
        img.style.transform = `rotate(${pos.rotate})`;
        div.appendChild(img);
      }

      const code = document.createElement('span');
      code.className = 'code';
      code.textContent = slot.slot_code || '';
      div.appendChild(code);

      if (slot.parking_type) {
        const meta = document.createElement('div');
        meta.className = 'meta';
        meta.textContent = slot.parking_type;
        div.appendChild(meta);
      }

if (!<?= $is_readonly ? 'true' : 'false' ?>) {
  div.addEventListener('click', () => onSlotClick(slot));
} else {
  div.style.cursor = 'default';
}
      return div;
    }

    function renderSlots(slots) {
      const byCode = {};
      slots.forEach(s => { byCode[(s.slot_code || '').toUpperCase()] = s; });

      const topRow = document.getElementById('topRow');
      const leftCol = document.getElementById('leftCol');
      const centerGrid = document.getElementById('centerGrid');
      const rightCol = document.getElementById('rightCol');
      const bottomLeft = document.getElementById('bottomLeft');
      const bottomRight = document.getElementById('bottomRight');

      [topRow, leftCol, centerGrid, rightCol, bottomLeft, bottomRight].forEach(c => c.innerHTML = '');

      function slotOrPlaceholder(code) {
        const s = byCode[code];
        if (s) return makeSlotElement(s);
        const ph = document.createElement('div');
        ph.className = 'slot vacant';
        const codeEl = document.createElement('span');
        codeEl.className = 'code';
        codeEl.textContent = code;
        ph.appendChild(codeEl);
        ph.addEventListener('click', () => showMessage('Missing slot in DB: ' + code, true));
        return ph;
      }

      layoutOrder.top.forEach(code => topRow.appendChild(slotOrPlaceholder(code)));
      layoutOrder.left.forEach(code => leftCol.appendChild(slotOrPlaceholder(code)));
      layoutOrder.center.forEach(code => centerGrid.appendChild(slotOrPlaceholder(code)));
      layoutOrder.right.forEach(code => rightCol.appendChild(slotOrPlaceholder(code)));
      layoutOrder.bottomLeft.forEach(code => bottomLeft.appendChild(slotOrPlaceholder(code)));
      layoutOrder.bottomRight.forEach(code => bottomRight.appendChild(slotOrPlaceholder(code)));
    }

    function onSlotClick(slot) {
      if (!canAssign) { showMessage('View only access', true); return; }
      const clientId = document.getElementById('client_select').value;
      if (!clientId) { showMessage('Please select a client first', true); return; }

      if (slot.status === 'occupied' || slot.status === 'unavailable') {
        alert(`Slot ${slot.slot_code} is ${slot.status}.`);
        return;
      }
      if (!confirm(`Assign client to ${slot.slot_code}?`)) return;

      fetch('assign_slot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ slot_id: slot.id, client_id: clientId })
      })
        .then(r => r.json())
        .then(resp => {
          if (resp.success) {
            showMessage(resp.message || 'Slot assigned');
            loadData();
            if (resp.ticket_id) window.open(`print_ticket.php?ticket_id=${encodeURIComponent(resp.ticket_id)}`, '_blank');
          } else {
            showMessage(resp.message || 'Assign failed', true);
          }
        }).catch(err => {
          console.error(err);
          showMessage('Request failed', true);
        });
    }

    document.getElementById('refreshBtn').addEventListener('click', () => loadData());
    loadData();
    pollTimer = setInterval(loadData, POLL_INTERVAL);
    
    window.addEventListener('resize', scaleMap);

function scaleMap() {
  const wrapper = document.querySelector('.map-wrapper');
  if (!wrapper) return;

  const screenWidth = window.innerWidth;
  let scale = 1;

  if (screenWidth < 1200 && screenWidth >= 1000) scale = 0.9;
  else if (screenWidth < 1000 && screenWidth >= 800) scale = 0.8;
  else if (screenWidth < 800 && screenWidth >= 650) scale = 0.7;
  else if (screenWidth < 650 && screenWidth >= 500) scale = 0.6;
  else if (screenWidth < 500 && screenWidth >= 400) scale = 0.5;
  else if (screenWidth < 400) scale = 0.45;

  // ✅ Universal zoom scaling
  wrapper.style.zoom = scale;

  // ✅ Optional Safari/Firefox fallback
  wrapper.style.transform = `scale(${scale})`;
  wrapper.style.transformOrigin = 'top center';
}
scaleMap();
window.addEventListener('resize', scaleMap);


  </script>

<?php include '../templates/footer.php'; ?>

