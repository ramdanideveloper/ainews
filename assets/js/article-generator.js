(function () {
  'use strict';

  const form = document.getElementById('aina-article-form');
  if (!form) return;

  const preview = document.getElementById('aina-article-preview');
  const message = document.getElementById('aina-article-message');
  const thumbnailToggle = form.elements.generate_thumbnail;
  const imageOptions = form.querySelector('.aina-image-options');
  const structureSelect = form.elements.structure;
  const pointCountField = form.querySelector('.aina-point-count');
  let draft = null;
  let thumbnail = null;

  const esc = value => String(value == null ? '' : value).replace(/[&<>'"]/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[char]));
  const money = value => 'Rp' + Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: 0});

  async function request(data) {
    const response = await fetch(ainaAdmin.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: data});
    const text = await response.text();
    let json;
    try { json = JSON.parse(text); } catch (error) { throw new Error('Server HTTP ' + response.status + ' tidak mengembalikan JSON yang valid.'); }
    if (!json.success) throw new Error(json.data && json.data.message ? json.data.message : ainaAdmin.error);
    return json.data;
  }

  function loading(title, detail) {
    message.className = 'aina-message aina-generating';
    message.innerHTML = '<span class="aina-editorial-loader"><i></i><i></i><i></i></span><span><strong>' + esc(title) + '</strong><small>' + esc(detail) + '</small></span>';
  }

  async function generateImage() {
    const data = new FormData();
    data.append('action', 'aina_generate_image');
    data.append('nonce', ainaAdmin.nonce);
    data.append('title', draft.main_title || form.elements.title.value);
    data.append('content_summary', draft.lead || form.elements.brief.value.slice(0, 1000));
    data.append('keyword', draft.seo && draft.seo.focus_keyword ? draft.seo.focus_keyword : form.elements.focus_keyword.value);
    data.append('image_prompt', form.elements.image_prompt.value);
    data.append('image_style', form.elements.image_style.value);
    data.append('aspect_ratio', form.elements.aspect_ratio.value);
    loading('Membuat thumbnail AI', 'Gemini Image sedang menyiapkan visual editorial…');
    thumbnail = await request(data);
  }

  function render(imageError) {
    const seo = draft.seo || {};
    const usage = draft.usage || {};
    const image = thumbnail ? '<figure class="aina-generated-figure"><img src="' + esc(thumbnail.image_url) + '" alt="' + esc(draft.main_title) + '"><figcaption>Thumbnail AI · ' + money(thumbnail.charged_amount) + '</figcaption></figure>' : '';
    const warning = imageError ? '<div class="aina-warning"><strong>Artikel berhasil, thumbnail gagal.</strong><p>' + esc(imageError) + '</p></div>' : '';
    const structureUsage = usage.point_target ? '<span>Jumlah poin <b>' + esc(usage.actual_points || 0) + ' / ' + esc(usage.point_target) + '</b></span><span>Status struktur <b>' + (usage.structure_met ? 'Sesuai' : 'Perlu diperbaiki') + '</b></span>' : '';
    preview.innerHTML = '<div class="aina-result-head"><div><span class="aina-step">02</span><h2>Preview Artikel</h2></div><span class="aina-badge">' + esc(draft.review_status) + '</span></div>' + warning + image + '<article class="aina-article"><h2>' + esc(draft.main_title) + '</h2><div class="aina-content">' + draft.content + '</div></article><div class="aina-article-meta-grid"><div><span>Focus keyword</span><strong>' + esc(seo.focus_keyword) + '</strong></div><div><span>SEO title</span><strong>' + esc(seo.seo_title) + '</strong></div><div><span>Meta description</span><p>' + esc(seo.meta_description) + '</p></div><div><span>Slug</span><strong>' + esc(seo.slug) + '</strong></div></div><div class="aina-usage-bar"><span>Jumlah kata <b>' + esc(usage.actual_words || 0) + ' / ' + esc(usage.word_target || form.elements.length.value) + '</b></span><span>Status target <b>' + (usage.target_met ? 'Tercapai' : 'Perlu bahan tambahan') + '</b></span>' + structureUsage + '<span>Input <b>' + esc(usage.input_tokens || 0) + '</b> token</span><span>Output <b>' + esc(usage.output_tokens || 0) + '</b> token</span><span>Biaya artikel <b>' + money(usage.charged_amount) + '</b></span><span>Saldo <b>' + money(usage.balance_after) + '</b></span></div><div class="aina-actions"><button type="button" class="button button-primary aina-primary" data-article-action="save">Simpan sebagai Draft</button><button type="button" class="button" data-article-action="regenerate">Regenerate Artikel</button><button type="button" class="button" data-article-action="image">' + (thumbnail ? 'Regenerate Thumbnail' : 'Generate Thumbnail') + '</button></div><div class="aina-message aina-preview-message"></div>';
    message.className = 'aina-message'; message.innerHTML = '';
  }

  async function generateArticle() {
    const button = form.querySelector('.aina-generate-article');
    const data = new FormData(form);
    data.append('action', 'aina_generate_article');
    data.append('nonce', ainaAdmin.nonce);
    button.disabled = true;
    button.innerHTML = '<span class="aina-button-spinner"></span> Menyusun Artikel';
    loading('Menyusun artikel SEO', 'Menganalisis brief, struktur, dan keyword…');
    try {
      const result = await request(data);
      draft = result.draft;
      thumbnail = null;
      let imageError = '';
      if (thumbnailToggle.checked) { try { await generateImage(); } catch (error) { imageError = error.message; } }
      render(imageError);
    } catch (error) {
      message.className = 'aina-message is-error'; message.textContent = error.message;
    } finally {
      button.disabled = false; button.textContent = draft ? 'Generate Ulang Artikel' : 'Generate Artikel';
    }
  }

  async function saveDraft(button) {
    const status = preview.querySelector('.aina-preview-message');
    const data = new FormData();
    data.append('action', 'aina_save_draft');
    data.append('nonce', ainaAdmin.nonce);
    data.append('draft', JSON.stringify(draft));
    if (thumbnail) data.append('thumbnail_id', thumbnail.attachment_id);
    button.disabled = true; button.textContent = ainaAdmin.saving;
    try {
      const result = await request(data);
      status.className = 'aina-message aina-preview-message is-success';
      status.innerHTML = esc(result.message) + ' <a href="' + esc(result.edit_url) + '">Edit artikel</a>';
    } catch (error) {
      status.className = 'aina-message aina-preview-message is-error'; status.textContent = error.message;
    } finally { button.disabled = false; button.textContent = 'Simpan sebagai Draft'; }
  }

  thumbnailToggle.addEventListener('change', () => { imageOptions.hidden = !thumbnailToggle.checked; });
  function syncStructureControl() {
    const controlled = structureSelect.value !== 'standard';
    pointCountField.hidden = !controlled;
    form.elements.point_count.disabled = !controlled;
  }
  structureSelect.addEventListener('change', syncStructureControl);
  syncStructureControl();
  form.addEventListener('submit', event => { event.preventDefault(); generateArticle(); });
  preview.addEventListener('click', async event => {
    const button = event.target.closest('[data-article-action]');
    if (!button || !draft) return;
    const action = button.dataset.articleAction;
    if (action === 'save') return saveDraft(button);
    if (action === 'regenerate') return generateArticle();
    if (action === 'image') {
      button.disabled = true;
      try { await generateImage(); render(''); }
      catch (error) { render(error.message); }
      finally { button.disabled = false; }
    }
  });
})();
