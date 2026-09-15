document.addEventListener('DOMContentLoaded', function () {
  var compareRail = document.getElementById('compareRail');
  var compareButton = document.getElementById('compareButton');
  var compareCount = document.getElementById('compareCount');
  var compareSelectors = document.querySelectorAll('.listing-card-selector');

  function updateCompareRail() {
    if (!compareRail || !compareButton || !compareCount || !compareSelectors.length) {
      return;
    }

    var selected = [];
    compareSelectors.forEach(function (selector) {
      if (selector.checked) {
        selected.push(selector.value);
      }
    });

    if (!selected.length) {
      compareRail.classList.remove('visible');
      compareButton.classList.add('disabled');
      compareButton.setAttribute('aria-disabled', 'true');
      compareCount.textContent = '0 selected';
      compareButton.setAttribute('href', '#');
      return;
    }

    compareRail.classList.add('visible');
    compareCount.textContent = selected.length + (selected.length === 1 ? ' seller selected' : ' sellers selected');

    if (selected.length >= 2 && selected.length <= 5) {
      compareButton.classList.remove('disabled');
      compareButton.setAttribute('aria-disabled', 'false');
      compareButton.setAttribute('href', 'compare.php?ids=' + selected.join(','));
    } else {
      compareButton.classList.add('disabled');
      compareButton.setAttribute('aria-disabled', 'true');
      compareButton.setAttribute('href', '#');
      compareCount.textContent = 'Select 2 to 5 sellers';
    }
  }

  if (compareSelectors.length) {
    compareSelectors.forEach(function (selector) {
      selector.addEventListener('change', function () {
        var checked = Array.prototype.slice.call(compareSelectors).filter(function (item) { return item.checked; });

        if (checked.length > 5) {
          selector.checked = false;
          updateCompareRail();
          return;
        }

        compareSelectors.forEach(function (item) {
          item.disabled = checked.length >= 5 && !item.checked;
        });

        updateCompareRail();
      });
    });

    updateCompareRail();
  }

  // Mobile nav toggle
  var toggle = document.getElementById('navToggle');
  var nav = document.getElementById('siteNav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var isOpen = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      toggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
    });
    nav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open menu');
      });
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && nav.classList.contains('open')) {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open menu');
        toggle.focus();
      }
    });
  }

  // Landing-page mobile navigation: accessible toggle, escape support, and close after navigation.
  var landingToggle = document.getElementById('landingMenuToggle');
  var landingPanel = document.getElementById('landingMobilePanel');
  if (landingToggle && landingPanel) {
    var setLandingMenu = function (open) {
      landingPanel.classList.toggle('is-open', open);
      landingToggle.classList.toggle('is-open', open);
      landingToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      landingToggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
    };
    landingToggle.addEventListener('click', function () {
      setLandingMenu(!landingPanel.classList.contains('is-open'));
    });
    landingPanel.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () { setLandingMenu(false); });
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && landingPanel.classList.contains('is-open')) {
        setLandingMenu(false);
        landingToggle.focus();
      }
    });
  }

  // Listing detail gallery thumbnail switcher
  var mainImg = document.querySelector('.detail-gallery-main img');
  var thumbs = document.querySelectorAll('.detail-thumbs img');
  thumbs.forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      if (mainImg) mainImg.src = thumb.dataset.full;
      thumbs.forEach(function (t) { t.classList.remove('active'); });
      thumb.classList.add('active');
    });
  });

  // Character counter for description field
  var desc = document.getElementById('description');
  var counter = document.getElementById('descCounter');
  if (desc && counter) {
    var update = function () { counter.textContent = desc.value.length + ' / 1500'; };
    desc.addEventListener('input', update);
    update();
  }
});
