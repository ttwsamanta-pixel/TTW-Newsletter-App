'use strict';
(() => {
  const form = document.querySelector('[data-autosave-newsletter]');
  if (!form) return;
  const step = Number(new URL(location.href).searchParams.get('step'));
  if (Number.isInteger(step) && step >= 0 && step <= 5) activateStep(step);
  let importing = false;
  async function call(route, data) {
    const response = await fetch('api/index.php?route=' + encodeURIComponent(route), {
      method: data ? 'POST' : 'GET', credentials: 'same-origin',
      headers: {'X-CSRF-Token': window.ttwCsrf, ...(data ? {'Content-Type': 'application/json'} : {})},
      body: data ? JSON.stringify(data) : undefined,
    });
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Import failed.');
    return result;
  }
  document.querySelectorAll('[data-import-section]').forEach(box => {
    let jobId, timerId, stopped = false;
    const status = box.querySelector('[data-import-status]');
    const apply = box.querySelector('[data-import-apply]');
    const retry = box.querySelector('[data-import-retry]');
    const consent = box.querySelector('[data-import-warning]');
    const accepted = box.querySelector('[data-import-accept]');
    const activeStep = () => [...document.querySelectorAll('.section')].findIndex(s => s.classList.contains('active'));
    function lock(value) {
      importing = value;
      form.inert = value;
      // Keep progress review controls accessible while protecting the editor's unsaved fields.
      if (value) { form.inert = false; form.querySelectorAll('input,select,textarea,button').forEach(c => { c.dataset.importDisabled = c.disabled ? '1' : '0'; c.disabled = true; }); }
      else form.querySelectorAll('[data-import-disabled]').forEach(c => { c.disabled = c.dataset.importDisabled === '1'; delete c.dataset.importDisabled; });
      document.querySelectorAll('.step').forEach(b => b.disabled = value);
      [apply,retry,accepted,box.querySelector('[data-import-stop]')].forEach(c => c.disabled = false);
    }
    async function saveSection() {
      if (stopped) return;
      try {
        apply.disabled = true;
        box.querySelector('[data-import-stop]').disabled = true;
        const result = await call(`/automation/jobs/${jobId}/generate`, {accept_warnings: accepted.checked});
        location.href = `editor.php?id=${Number(result.newsletter_id)}&step=${activeStep()}`;
      } catch (e) { status.textContent = e.message; apply.disabled = false; lock(false); }
    }
    async function poll() {
      if (stopped) return;
      try {
        const currentId = jobId;
        const job = await call(`/automation/jobs/${currentId}`);
        if (stopped || currentId !== jobId) return;
        status.replaceChildren();
        const heading = document.createElement('p'); heading.textContent = `${job.completed}/${job.total} articles · ${job.status.replaceAll('_',' ')}`; status.append(heading);
        for (const article of job.articles) {
          const line = document.createElement('p');
          line.textContent = `${article.result?.title || article.url} — ${article.error_message || article.status}`; status.append(line);
        }
        if (job.status === 'completed') { await saveSection(); return; }
        if (['failed','completed_with_warnings'].includes(job.status)) {
          retry.hidden = false; consent.hidden = job.status !== 'completed_with_warnings';
          apply.hidden = job.status !== 'completed_with_warnings';
          apply.disabled = !accepted.checked; return;
        }
        timerId = setTimeout(poll,2500);
      } catch (e) { status.textContent = e.message; lock(false); }
    }
    box.querySelector('[data-import-start]').onclick = async () => {
      if (importing || newsletterSaving) return;
      stopped = false;
      clearTimeout(timerId);
      const text = box.querySelector('[data-import-links]').value;
      const request = {input_mode:'section',section:box.dataset.importSection,text};
      try {
        const parsed = await call('/automation/parse',request);
        if (parsed.errors.length) throw new Error(parsed.errors.join('\n'));
        const invalid = [...form.querySelectorAll('[required]')].find(c => !c.checkValidity());
        if (invalid) { invalid.reportValidity(); return; }
        const body = new FormData(form); body.set('autosave','1');
        lock(true); status.textContent = 'Saving your draft before importing…';
        const response = await fetch(form.action,{method:'POST',body,credentials:'same-origin'});
        const saved = await response.json();
        if (!response.ok || !saved.id) throw new Error(saved.error || 'Save the draft before importing. If this edition exists, open it from Newsletters.');
        form.querySelector('[name=id]').value = saved.id;
        document.querySelector('#currentNewsletterId').value = saved.id;
        const current = new URL(location.href); current.searchParams.set('id',saved.id); history.replaceState(null,'',current);
        if (stopped) return;
        const job = await call('/automation/jobs',{...request,target_id:Number(saved.id),newsletter_date:body.get('newsletter_date'),edition:body.get('edition')});
        jobId = job.id; accepted.checked = false; await poll();
      } catch (e) { status.textContent = e.message; lock(false); }
    };
    accepted.onchange = () => apply.disabled = !accepted.checked;
    box.querySelector('[data-import-stop]').onclick = () => {
      stopped = true; clearTimeout(timerId); lock(false); apply.hidden = true; retry.hidden = true; consent.hidden = true;
      status.textContent = 'Editor unlocked. The queued job remains in Automation jobs; it will not replace this section until you choose to apply it.';
    };
    apply.onclick = saveSection;
    retry.onclick = async () => {
      try { await call(`/automation/jobs/${jobId}/retry`,{}); retry.hidden = true; apply.hidden = true; consent.hidden = true; accepted.checked = false; await poll(); }
      catch (e) { status.textContent = e.message; lock(false); }
    };
  });
})();
