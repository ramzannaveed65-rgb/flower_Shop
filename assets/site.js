// =====================================================
// site.js - Small helpers for the customer website
// =====================================================

// Plus / minus buttons next to a quantity box
document.querySelectorAll('.qty').forEach(function (box) {
  var input = box.querySelector('input');
  box.querySelectorAll('button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var min = parseInt(input.min || '1', 10);
      var max = parseInt(input.max || '99', 10);
      var value = (parseInt(input.value, 10) || min) + (btn.dataset.step === 'up' ? 1 : -1);
      input.value = Math.max(min, Math.min(max, value));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });
});

// Cart page: save the new quantity as soon as it changes
document.querySelectorAll('form[data-autosubmit] input[name="quantity"]').forEach(function (input) {
  input.addEventListener('change', function () { input.form.submit(); });
});

// Home page: "Order within 2 h 15 min for delivery today" counts down by itself
var today = document.querySelector('[data-minutes-left]');
if (today) {
  var left = parseInt(today.dataset.minutesLeft, 10);
  var label = today.querySelector('[data-countdown]');
  var show = function () {
    if (left <= 0) { window.location.reload(); return; }
    var h = Math.floor(left / 60), m = left % 60;
    label.textContent = (h > 0 ? h + ' h ' : '') + m + ' min';
  };
  show();
  setInterval(function () { left -= 1; show(); }, 60000);
}

// Checkout: the delivery date is only needed for standard delivery,
// and same-day is only offered in the same-day cities
var checkout = document.querySelector('[data-checkout]');
if (checkout) {
  var sameDayCities = JSON.parse(checkout.dataset.sameDayCities || '[]').map(function (c) { return c.toLowerCase(); });
  var sameDayOpen = checkout.dataset.sameDayOpen === '1';
  var charges = { standard: parseFloat(checkout.dataset.standard), same_day: parseFloat(checkout.dataset.sameDay) };
  var subtotal = parseFloat(checkout.dataset.subtotal);
  var money = function (n) { return 'Rs ' + Math.round(n).toLocaleString('en-US'); };

  var refresh = function () {
    var city = (checkout.querySelector('input[name="city"]:checked') || {}).value || '';
    var sameDay = checkout.querySelector('input[name="delivery_type"][value="same_day"]');
    var standard = checkout.querySelector('input[name="delivery_type"][value="standard"]');
    var allowed = sameDayOpen && sameDay.dataset.itemsOk === '1' && (city === '' || sameDayCities.indexOf(city.toLowerCase()) !== -1);
    sameDay.disabled = !allowed;
    if (!allowed && sameDay.checked) { standard.checked = true; }

    var type = sameDay.checked ? 'same_day' : 'standard';
    checkout.querySelector('[data-date-row]').hidden = (type === 'same_day');
    checkout.querySelector('[data-delivery]').textContent = money(charges[type]);
    checkout.querySelectorAll('[data-total]').forEach(function (el) { el.textContent = money(subtotal + charges[type]); });
  };
  checkout.addEventListener('change', refresh);
  refresh();
}
