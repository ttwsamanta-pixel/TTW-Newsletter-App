const qs = (selector, scope = document) => scope.querySelector(selector);
const qsa = (selector, scope = document) => [...scope.querySelectorAll(selector)];

let newsletterSaving = false;

async function showStep(index) {
  if (newsletterSaving) return;
  const form = qs('[data-autosave-newsletter]');
  const currentStep = qsa('.section').findIndex(section => section.classList.contains('active'));
  if (form && index > currentStep) {
    const invalid = qsa('input, select, textarea', form).find(input => !input.checkValidity());
    if (invalid) {
      const sectionIndex = qsa('.section').indexOf(invalid.closest('.section'));
      if (sectionIndex >= 0) activateStep(sectionIndex);
      invalid.reportValidity();
      return;
    }
    const status = qs('[data-save-status]', form);
    const controls = qsa('button', form).concat(qsa('.step'));
    const previousDisabled = controls.map(button => button.disabled);
    newsletterSaving = true;
    controls.forEach(button => button.disabled = true);
    status.hidden = false;
    status.textContent = 'Saving draft...';
    try {
      const data = new FormData(form);
      data.set('autosave', '1');
      const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin' });
      if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
        throw new Error('Unable to save. Check your connection and sign-in, then try Next again.');
      }
      const result = await response.json();
      if (!response.ok || !result.id) throw new Error(result.error || 'Draft could not be saved. Please try again.');
      qs('[name="id"]', form).value = result.id;
      qs('#currentNewsletterId').value = result.id;
      const url = new URL(window.location.href);
      url.searchParams.set('id', result.id);
      window.history.replaceState(null, '', url);
      status.textContent = 'Draft saved.';
    } catch (error) {
      status.textContent = error.message;
      return;
    } finally {
      newsletterSaving = false;
      controls.forEach((button, i) => button.disabled = previousDisabled[i]);
    }
  }
  activateStep(index);
}

function activateStep(index) {
  qsa('.section').forEach((section, i) => section.classList.toggle('active', i === index));
  qsa('.step').forEach((step, i) => step.classList.toggle('active', i === index));
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function addItem(templateId, targetId) {
  const template = qs(`#${templateId}`);
  const target = qs(`#${targetId}`);
  const node = template.content.firstElementChild.cloneNode(true);
  if (node.matches('[data-category]')) {
    prepareCategory(node);
  }
  target.appendChild(node);
  initCroppers(node);
  initSortableLists(node);
  refreshOrderNumbers(target);
}

function removeItem(button) {
  const list = button.closest('.repeat-list');
  button.closest('.repeat-item').remove();
  if (list) refreshOrderNumbers(list);
}

function addCategoryNews(button) {
  const category = button.closest('[data-category]');
  const key = category.dataset.category;
  const holder = qs('.category-items', category);
  const template = qs('#categoryNewsTemplate').innerHTML.replaceAll('__KEY__', key);
  holder.insertAdjacentHTML('beforeend', template);
  initSortableLists(holder);
  refreshOrderNumbers(holder);
}

function prepareCategory(node) {
  const key = `cat_${Math.random().toString(36).slice(2)}`;
  node.dataset.category = key;
  qsa('[data-category-key]', node).forEach((input) => input.value = key);
  qsa('[name*="__KEY__"]', node).forEach((input) => {
    input.name = input.name.replace('__KEY__', key);
  });
}

function initCroppers(scope = document) {
  qsa('[data-cropper]', scope).forEach((box) => {
    if (box.dataset.ready) return;
    box.dataset.ready = '1';

    const file = qs('input[type=file]', box);
    const hidden = qs('input[type=hidden]', box);
    const canvas = qs('canvas', box);
    const existingPreview = qs('.crop-existing-preview', box);
    const zoom = qs('[data-zoom]', box);
    const ctx = canvas.getContext('2d');
    const ratio = Number(box.dataset.ratio || 1);
    const width = Number(box.dataset.width || 610);
    const height = Math.round(width / ratio);
    const originalMode = box.dataset.mode === 'original';
    const state = { img: null, scale: 1, x: 0, y: 0, drag: false, sx: 0, sy: 0, original: '' };

    canvas.width = width;
    canvas.height = height;
    if (existingPreview) {
      canvas.style.display = 'none';
    }

    const draw = () => {
      ctx.fillStyle = '#e8edf5';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      if (!state.img) {
        ctx.fillStyle = '#53627a';
        ctx.font = '18px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('Paste or choose image', canvas.width / 2, canvas.height / 2);
        return;
      }
      const base = Math.max(canvas.width / state.img.width, canvas.height / state.img.height);
      const scale = base * state.scale;
      const w = state.img.width * scale;
      const h = state.img.height * scale;
      const minX = canvas.width - w;
      const minY = canvas.height - h;
      state.x = Math.min(0, Math.max(minX, state.x));
      state.y = Math.min(0, Math.max(minY, state.y));
      ctx.drawImage(state.img, state.x, state.y, w, h);
      hidden.value = originalMode ? state.original : canvas.toDataURL('image/jpeg', 0.9);
    };

    const load = (src) => {
      if (existingPreview) existingPreview.style.display = 'none';
      canvas.style.display = 'block';
      const img = new Image();
      img.onload = () => {
        state.img = img;
        state.original = src;
        state.scale = Number((zoom && zoom.value) || 1);
        const base = Math.max(canvas.width / img.width, canvas.height / img.height);
        state.x = (canvas.width - img.width * base * state.scale) / 2;
        state.y = (canvas.height - img.height * base * state.scale) / 2;
        draw();
      };
      img.src = src;
    };

    file.addEventListener('change', () => {
      const chosen = file.files[0];
      if (!chosen) return;
      const reader = new FileReader();
      reader.onload = () => load(reader.result);
      reader.readAsDataURL(chosen);
    });
    box.addEventListener('paste', (event) => {
      const image = [...event.clipboardData.items].find((item) => item.type.startsWith('image/'));
      if (!image) return;
      const reader = new FileReader();
      reader.onload = () => load(reader.result);
      reader.readAsDataURL(image.getAsFile());
    });
    if (zoom) {
      zoom.addEventListener('input', () => {
        state.scale = Number(zoom.value);
        draw();
      });
    }
    canvas.addEventListener('pointerdown', (event) => {
      if (originalMode) return;
      state.drag = true;
      state.sx = event.offsetX - state.x;
      state.sy = event.offsetY - state.y;
      canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', (event) => {
      if (!state.drag || originalMode) return;
      state.x = event.offsetX - state.sx;
      state.y = event.offsetY - state.sy;
      draw();
    });
    canvas.addEventListener('pointerup', () => state.drag = false);
    draw();
  });
}

function initOriginalUploads(scope = document) {
  qsa('[data-original-upload]', scope).forEach((box) => {
    if (box.dataset.ready) return;
    box.dataset.ready = '1';

    const file = qs('input[type=file]', box);
    const hidden = qs('input[type=hidden]', box);
    const image = qs('img', box);
    const label = qs('span', box);
    const existingSrc = box.dataset.existingSrc || '';

    const load = (src) => {
      hidden.value = src;
      image.src = src;
      image.style.display = 'block';
      label.textContent = 'Original image ready. Change width/height only if needed.';
    };

    if (existingSrc) {
      image.src = existingSrc;
      image.style.display = 'block';
      label.textContent = 'Saved promotion image loaded. Paste or choose new image to replace.';
    }

    file.addEventListener('change', () => {
      const chosen = file.files[0];
      if (!chosen) return;
      const reader = new FileReader();
      reader.onload = () => load(reader.result);
      reader.readAsDataURL(chosen);
    });

    box.addEventListener('paste', (event) => {
      const clipboardImage = [...event.clipboardData.items].find((item) => item.type.startsWith('image/'));
      if (!clipboardImage) return;
      const reader = new FileReader();
      reader.onload = () => load(reader.result);
      reader.readAsDataURL(clipboardImage.getAsFile());
    });
  });
}

function initEditionAvailability() {
  const dateInput = qs('#newsletterDate');
  const editionSelect = qs('#editionSelect');
  const currentId = Number(qs('#currentNewsletterId')?.value || 0);
  const note = qs('#editionNote');
  const existing = window.existingNewsletterEditions || {};

  if (!dateInput || !editionSelect) return;

  const refresh = () => {
    const date = dateInput.value;
    const used = existing[date] || [];
    const disabled = new Set(
      used
        .filter((issue) => Number(issue.id) !== currentId)
        .map((issue) => issue.edition)
    );

    qsa('option', editionSelect).forEach((option) => {
      option.disabled = disabled.has(option.value);
    });

    if (editionSelect.selectedOptions[0]?.disabled) {
      const available = qsa('option', editionSelect).find((option) => !option.disabled);
      if (available) editionSelect.value = available.value;
    }

    if (note) {
      if (disabled.has('M') && !disabled.has('E')) {
        note.textContent = 'Morning already exists for this date. Please create Evening or edit the Morning issue.';
      } else if (disabled.has('E') && !disabled.has('M')) {
        note.textContent = 'Evening already exists for this date. Please create Morning or edit the Evening issue.';
      } else if (disabled.has('M') && disabled.has('E')) {
        note.textContent = 'Morning and Evening already exist for this date. Open the existing newsletter from Newsletters to edit.';
      } else {
        note.textContent = '';
      }
    }
  };

  dateInput.addEventListener('change', refresh);
  refresh();
}

function initSortableLists(scope = document) {
  qsa('.repeat-list', scope).forEach((list) => {
    if (list.dataset.sortable) return;
    list.dataset.sortable = '1';
    let dragged = null;

    refreshOrderNumbers(list);

    list.addEventListener('change', (event) => {
      if (!event.target.matches('.sort-order')) return;
      moveByNumber(event.target);
    });

    list.addEventListener('dragstart', (event) => {
      const item = event.target.closest('.repeat-item');
      if (!item || item.parentElement !== list || !item.hasAttribute('draggable')) return;
      dragged = item;
      item.classList.add('dragging');
      event.dataTransfer.effectAllowed = 'move';
    });

    list.addEventListener('dragend', () => {
      if (dragged) {
        dragged.classList.remove('dragging');
      }
      refreshOrderNumbers(list);
      dragged = null;
    });

    list.addEventListener('dragover', (event) => {
      if (!dragged) return;
      event.preventDefault();
      const after = getDragAfterElement(list, event.clientY);
      if (!after) {
        list.appendChild(dragged);
      } else {
        list.insertBefore(dragged, after);
      }
    });
  });
}

function refreshOrderNumbers(list) {
  qsa(':scope > .repeat-item', list).forEach((item, index) => {
    const input = qs(':scope > .repeat-head .sort-order', item);
    if (input) input.value = index + 1;
  });
}

function moveByNumber(input) {
  const item = input.closest('.repeat-item');
  const list = item.parentElement;
  const items = qsa(':scope > .repeat-item', list);
  const targetIndex = Math.max(0, Math.min(items.length - 1, Number(input.value || 1) - 1));
  const currentIndex = items.indexOf(item);

  if (targetIndex === currentIndex) {
    refreshOrderNumbers(list);
    return;
  }

  const reference = items[targetIndex];
  if (targetIndex > currentIndex) {
    list.insertBefore(item, reference.nextSibling);
  } else {
    list.insertBefore(item, reference);
  }
  refreshOrderNumbers(list);
}

function getDragAfterElement(container, y) {
  const items = qsa(':scope > .repeat-item:not(.dragging)', container);
  return items.reduce((closest, child) => {
    const box = child.getBoundingClientRect();
    const offset = y - box.top - box.height / 2;
    if (offset < 0 && offset > closest.offset) {
      return { offset, element: child };
    }
    return closest;
  }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
}

async function copyTextToClipboard(text) {
  if (navigator.clipboard && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch (error) {
      // Some browsers block the async clipboard API despite HTTPS.
    }
  }

  const textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.style.position = 'fixed';
  textarea.style.left = '-9999px';
  textarea.style.top = '0';
  textarea.setAttribute('readonly', '');
  document.body.appendChild(textarea);
  textarea.focus();
  textarea.select();
  textarea.setSelectionRange(0, textarea.value.length);
  const copied = document.execCommand('copy');
  textarea.remove();
  return copied;
}

function initCopyHtmlButtons() {
  qsa('[data-copy-html]').forEach((button) => {
    if (button.dataset.ready) return;
    button.dataset.ready = '1';

    button.addEventListener('click', async () => {
      const originalText = button.textContent;
      button.disabled = true;
      button.textContent = 'Copying...';

      try {
        const response = await fetch(button.dataset.copyHtml, { credentials: 'same-origin' });
        if (!response.ok) throw new Error('Unable to load HTML');
        const html = await response.text();
        const copied = await copyTextToClipboard(html);
        if (!copied) throw new Error('Clipboard copy was blocked');
        button.textContent = 'Copied';
        setTimeout(() => {
          button.textContent = originalText;
          button.disabled = false;
        }, 1600);
      } catch (error) {
        button.textContent = 'Copy failed';
        setTimeout(() => {
          button.textContent = originalText;
          button.disabled = false;
        }, 2200);
      }
    });
  });
}

function initBulkNewsletterSelection() {
  const form = qs('#newsletterBulkForm');
  const selectAll = qs('#selectAllNewsletters');
  const deleteButton = qs('#bulkDeleteButton');
  const checkboxes = qsa('.newsletter-select');

  if (!form || !selectAll || !deleteButton || !checkboxes.length) return;

  const refresh = () => {
    const checked = checkboxes.filter((checkbox) => checkbox.checked).length;
    deleteButton.disabled = checked === 0;
    selectAll.checked = checked === checkboxes.length;
    selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
  };

  selectAll.addEventListener('change', () => {
    checkboxes.forEach((checkbox) => {
      checkbox.checked = selectAll.checked;
    });
    refresh();
  });

  checkboxes.forEach((checkbox) => checkbox.addEventListener('change', refresh));
  refresh();
}

document.addEventListener('DOMContentLoaded', () => {
  qs('[data-autosave-newsletter]')?.addEventListener('submit', event => {
    if (newsletterSaving) event.preventDefault();
  });
  initCroppers();
  initOriginalUploads();
  initSortableLists();
  initEditionAvailability();
  initCopyHtmlButtons();
  initBulkNewsletterSelection();
});
