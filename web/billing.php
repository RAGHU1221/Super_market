<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_permission('billing');

$categories = db()->query("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$products = db()->query("SELECT id, name, name_ta, barcode, sku, unit, selling_price, tax_percent, stock_qty, reorder_level, category_id FROM products WHERE deleted_at IS NULL AND is_active=1 ORDER BY name")->fetchAll();
$heldBills = db()->query("SELECT * FROM held_bills ORDER BY id DESC")->fetchAll();
$s = get_setting();

$pageTitle = t('billing');
$activeModule = 'billing';
require_once __DIR__ . '/includes/header.php';
?>
<div class="pos-layout">
  <div class="pos-products">
    <div class="card" style="margin-bottom:.8rem;">
      <input type="text" id="scan-input" placeholder="🔍 Scan barcode or search product name / பொருள் தேடு..." autofocus style="font-size:1rem;">
      <div style="display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.4rem;">
        <button class="btn btn-sm cat-btn active" data-cat="">All</button>
        <?php foreach ($categories as $c): ?>
          <button class="btn btn-sm cat-btn" data-cat="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="product-grid" id="product-grid"></div>
  </div>

  <div class="pos-cart">
    <div class="card">
      <div class="card-title">
        🛒 <?= e(t('cart')) ?>
        <button class="btn btn-sm" onclick="document.getElementById('held-modal').style.display='flex'">📋 Held (<?= count($heldBills) ?>)</button>
      </div>
      <div style="margin-bottom:.6rem;">
        <input type="text" id="customer-name" placeholder="Customer name (optional)" style="margin-bottom:.4rem;">
        <input type="text" id="customer-phone" placeholder="Customer phone (optional)">
      </div>
      <div id="cart-lines" style="max-height:32vh; overflow-y:auto;"></div>
      <div style="margin-top:.6rem;">
        <label>Overall Discount (₹)</label>
        <input type="number" step="0.01" id="discount-input" value="0" style="margin-bottom:.4rem;">
      </div>
      <div class="cart-totals">
        <div><span><?= e(t('total')) ?></span><span id="sub-total">₹0.00</span></div>
        <div><span><?= e(t('discount')) ?></span><span id="disc-total">₹0.00</span></div>
        <div><span><?= e(t('tax')) ?></span><span id="tax-total">₹0.00</span></div>
        <div class="grand"><span><?= e(t('grand_total')) ?></span><span id="grand-total">₹0.00</span></div>
      </div>
      <div style="display:flex; gap:.5rem; margin-top:.8rem;">
        <div style="flex:1;"><label>Payment Mode</label>
          <select id="payment-mode">
            <option value="cash"><?= e(t('cash')) ?></option>
            <option value="card"><?= e(t('card')) ?></option>
            <option value="upi"><?= e(t('upi')) ?></option>
            <option value="credit"><?= e(t('credit')) ?></option>
          </select>
        </div>
        <div style="flex:1;"><label>Paid Amount</label><input type="number" step="0.01" id="paid-amount"></div>
      </div>
      <button class="btn btn-gold btn-block" style="margin-top:.6rem;" onclick="holdBill()">⏸️ <?= e(t('hold_bill')) ?></button>
      <button class="btn btn-primary btn-block" style="margin-top:.5rem; font-size:1.05rem; padding:.8rem;" onclick="checkout()">✅ <?= e(t('pay')) ?></button>
    </div>
  </div>
</div>

<!-- Held bills modal -->
<div id="held-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); align-items:center; justify-content:center; z-index:50;">
  <div class="card" style="width:90%; max-width:600px; max-height:80vh; overflow-y:auto;">
    <div class="card-title">📋 Held Bills <button class="btn btn-sm" onclick="document.getElementById('held-modal').style.display='none'">✕</button></div>
    <div id="held-list">
      <?php foreach ($heldBills as $h): ?>
        <div class="cart-line">
          <span><?= e($h['hold_ref']) ?> — <?= e($h['customer_name'] ?: 'Walk-in') ?> (<?= date('h:i A', strtotime($h['created_at'])) ?>)</span>
          <span>
            <button class="btn btn-sm btn-primary" onclick='resumeHold(<?= (int)$h["id"] ?>, <?= json_encode($h["cart_json"]) ?>)'>Resume</button>
            <button class="btn btn-sm btn-danger" onclick="deleteHold(<?= (int)$h['id'] ?>)">✕</button>
          </span>
        </div>
      <?php endforeach; ?>
      <?php if (!$heldBills): ?><p style="color:var(--ink-500);">No held bills.</p><?php endif; ?>
    </div>
  </div>
</div>

<script>
const PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
const TAX_MODE = 'exclusive'; // selling_price excludes tax; tax added on top
let cart = []; // {id, name, price, tax_percent, qty, stock_qty, unit}
let activeCat = '';

function renderGrid(filterText) {
  const grid = document.getElementById('product-grid');
  filterText = (filterText || '').toLowerCase();
  const items = PRODUCTS.filter(p => {
    if (activeCat && String(p.category_id) !== activeCat) return false;
    if (!filterText) return true;
    return p.name.toLowerCase().includes(filterText) ||
           (p.name_ta || '').includes(filterText) ||
           (p.barcode || '').includes(filterText) ||
           (p.sku || '').toLowerCase().includes(filterText);
  }).slice(0, 120);
  grid.innerHTML = items.map(p => `
    <div class="product-tile" onclick="addToCart(${p.id})">
      <div>${p.name}</div>
      <div class="p-name">${p.name_ta || ''}</div>
      <div class="p-price">₹${parseFloat(p.selling_price).toFixed(2)}</div>
      ${p.stock_qty <= p.reorder_level ? '<div class="p-stock-low">Low stock: '+p.stock_qty+'</div>' : ''}
    </div>`).join('') || '<p style="color:var(--ink-500);">No products found.</p>';
}

document.querySelectorAll('.cat-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeCat = btn.dataset.cat;
    renderGrid(document.getElementById('scan-input').value);
  });
});

const scanInput = document.getElementById('scan-input');
scanInput.addEventListener('input', () => renderGrid(scanInput.value));
scanInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    // barcode scanners send digits then Enter
    const val = scanInput.value.trim();
    const exact = PRODUCTS.find(p => p.barcode && p.barcode === val);
    if (exact) {
      addToCart(exact.id);
      scanInput.value = '';
      renderGrid('');
    }
  }
});

function addToCart(id) {
  const p = PRODUCTS.find(x => x.id === id);
  if (!p) return;
  let line = cart.find(c => c.id === id);
  if (line) { line.qty += 1; } else {
    cart.push({ id: p.id, name: p.name, price: parseFloat(p.selling_price), tax_percent: parseFloat(p.tax_percent), qty: 1, stock_qty: parseFloat(p.stock_qty), unit: p.unit });
  }
  renderCart();
}

function changeQty(id, delta) {
  const line = cart.find(c => c.id === id);
  if (!line) return;
  line.qty += delta;
  if (line.qty <= 0) cart = cart.filter(c => c.id !== id);
  renderCart();
}

function removeLine(id) { cart = cart.filter(c => c.id !== id); renderCart(); }

function renderCart() {
  const el = document.getElementById('cart-lines');
  el.innerHTML = cart.map(c => `
    <div class="cart-line">
      <span>${c.name}<br><small>₹${c.price.toFixed(2)} x ${c.qty}</small></span>
      <span style="display:flex; align-items:center; gap:.3rem;">
        <button class="cart-qty-btn" onclick="changeQty(${c.id},-1)">-</button>
        <b>${c.qty}</b>
        <button class="cart-qty-btn" onclick="changeQty(${c.id},1)">+</button>
        <button class="btn btn-sm btn-danger" onclick="removeLine(${c.id})">✕</button>
      </span>
    </div>`).join('') || '<p style="color:var(--ink-500);">Cart is empty.</p>';
  calcTotals();
}

function calcTotals() {
  let sub = 0, tax = 0;
  cart.forEach(c => {
    const lineSub = c.price * c.qty;
    sub += lineSub;
    tax += lineSub * (c.tax_percent / 100);
  });
  const discount = parseFloat(document.getElementById('discount-input').value) || 0;
  const grand = Math.max(0, sub + tax - discount);
  document.getElementById('sub-total').textContent = '₹' + sub.toFixed(2);
  document.getElementById('disc-total').textContent = '₹' + discount.toFixed(2);
  document.getElementById('tax-total').textContent = '₹' + tax.toFixed(2);
  document.getElementById('grand-total').textContent = '₹' + grand.toFixed(2);
  document.getElementById('paid-amount').value = grand.toFixed(2);
  return { sub, tax, discount, grand };
}
document.getElementById('discount-input').addEventListener('input', calcTotals);

function holdBill() {
  if (!cart.length) { alert('Cart is empty.'); return; }
  fetch('billing_hold.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
    body: JSON.stringify({
      action: 'hold',
      customer_name: document.getElementById('customer-name').value,
      cart: cart
    })
  }).then(r => r.json()).then(res => {
    if (res.ok) { cart = []; renderCart(); location.reload(); } else alert(res.error || 'Failed to hold bill.');
  });
}

function resumeHold(id, cartJson) {
  cart = JSON.parse(cartJson);
  renderCart();
  fetch('billing_hold.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
    body: JSON.stringify({ action: 'delete', id: id })
  }).then(() => { document.getElementById('held-modal').style.display = 'none'; });
}

function deleteHold(id) {
  fetch('billing_hold.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
    body: JSON.stringify({ action: 'delete', id: id })
  }).then(() => location.reload());
}

function checkout() {
  if (!cart.length) { alert('Cart is empty. / கார்ட் காலியாக உள்ளது.'); return; }
  const totals = calcTotals();
  const payload = {
    customer_name: document.getElementById('customer-name').value,
    customer_phone: document.getElementById('customer-phone').value,
    discount_amount: totals.discount,
    payment_mode: document.getElementById('payment-mode').value,
    paid_amount: parseFloat(document.getElementById('paid-amount').value) || 0,
    items: cart
  };
  fetch('billing_process.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
    body: JSON.stringify(payload)
  }).then(r => r.json()).then(res => {
    if (res.ok) {
      cart = [];
      renderCart();
      window.location.href = 'invoice.php?id=' + res.sale_id + '&print=1';
    } else {
      alert(res.error || 'Checkout failed.');
    }
  }).catch(() => alert('Network error. Please try again.'));
}

renderGrid('');
renderCart();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
