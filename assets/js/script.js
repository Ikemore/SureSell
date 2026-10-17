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

  // Latest-listings dialog on the landing page.
  var howTrigger = document.getElementById('howItWorksTrigger');
  var howDialog = document.getElementById('howItWorksDialog');
  var howClose = document.getElementById('howItWorksClose');
  if (howTrigger && howDialog) {
    howTrigger.addEventListener('click', function () { howDialog.showModal(); });
    if (howClose) howClose.addEventListener('click', function () { howDialog.close(); });
    howDialog.addEventListener('click', function (event) {
      if (event.target === howDialog) howDialog.close();
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

  // Live listing card preview and local photo thumbnails on the sell page.
  var sellForm = document.getElementById('sellForm');
  if (sellForm) {
    var previewTitle = document.getElementById('sellPreviewTitle');
    var previewCategory = document.getElementById('sellPreviewCategory');
    var previewPrice = document.getElementById('sellPreviewPrice');
    var previewTown = document.getElementById('sellPreviewTown');
    var previewImage = document.getElementById('sellPreviewImage');
    var titleInput = sellForm.querySelector('[data-preview-title]');
    var categoryInput = sellForm.querySelector('[data-preview-category]');
    var priceInput = sellForm.querySelector('[data-preview-price]');
    var townInput = sellForm.querySelector('[data-preview-town]');
    var negotiableInput = sellForm.querySelector('[data-preview-negotiable]');
    var imageInput = document.getElementById('images');
    var photoGrid = document.getElementById('sellPhotoPreview');
    var photoUrls = [];

    function updateSellPreview() {
      var title = titleInput.value.trim();
      var category = categoryInput.options[categoryInput.selectedIndex];
      var price = Number(priceInput.value);
      previewTitle.textContent = title || 'Your item title';
      previewCategory.textContent = category && category.value ? category.text : 'Your category';
      previewPrice.textContent = price > 0 ? 'GH₵ ' + price.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (negotiableInput.checked ? ' · Negotiable' : '') : 'GH₵ —';
      previewTown.textContent = townInput.value.trim() || 'Your location';
    }

    [titleInput, categoryInput, priceInput, townInput, negotiableInput].forEach(function (input) {
      input.addEventListener('input', updateSellPreview);
      input.addEventListener('change', updateSellPreview);
    });
    updateSellPreview();

    if (imageInput && photoGrid) {
      imageInput.addEventListener('change', function () {
        photoUrls.forEach(function (url) { URL.revokeObjectURL(url); });
        photoUrls = [];
        photoGrid.textContent = '';
        var files = Array.prototype.slice.call(imageInput.files || []).slice(0, 5);
        files.forEach(function (file, index) {
          if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return;
          var url = URL.createObjectURL(file);
          photoUrls.push(url);
          var tile = document.createElement('div');
          tile.className = 'sell-photo-thumb';
          var image = document.createElement('img');
          image.src = url;
          image.alt = 'Selected listing photo ' + (index + 1);
          tile.appendChild(image);
          if (index === 0) {
            var badge = document.createElement('span');
            badge.textContent = 'Cover';
            tile.appendChild(badge);
          }
          photoGrid.appendChild(tile);
        });
        if (photoUrls.length) {
          previewImage.textContent = '';
          var mainPhoto = document.createElement('img');
          mainPhoto.src = photoUrls[0];
          mainPhoto.alt = 'Preview of your first listing photo';
          previewImage.appendChild(mainPhoto);
        } else {
          previewImage.textContent = 'PHOTO PREVIEW';
        }
      });
    }
  }

  // Show/hide all password fields with a toggle control.
  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    if (input.dataset.passwordToggleBound === '1') {
      return;
    }

    input.dataset.passwordToggleBound = '1';
    var wrap = document.createElement('div');
    wrap.className = 'password-field-wrap';

    if (input.parentNode) {
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
    }

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'password-toggle';
    toggle.textContent = 'Show';
    toggle.setAttribute('aria-label', 'Show password');
    toggle.addEventListener('click', function () {
      var shouldShow = input.type === 'password';
      input.type = shouldShow ? 'text' : 'password';
      toggle.textContent = shouldShow ? 'Hide' : 'Show';
      toggle.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
      input.focus();
      var valueLength = input.value.length;
      if (valueLength > 0) {
        input.setSelectionRange(valueLength, valueLength);
      }
    });

    wrap.appendChild(toggle);
  });
});
