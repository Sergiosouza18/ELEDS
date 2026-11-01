// Lista de orçamento: itens, valores, desconto, WhatsApp e PDF. Tudo fica no navegador de quem usa.
(function () {
  const KEY = 'he_orcamento_v1', KEYD = 'he_orcamento_desc_v1';
  const load = () => { try { const a = JSON.parse(localStorage.getItem(KEY) || '[]'); return Array.isArray(a) ? a.filter(i => i && i.code).map(i => ({ code: String(i.code), name: String(i.name || ''), qty: Math.max(1, parseInt(i.qty, 10) || 1), price: (i.price != null && isFinite(i.price) && i.price >= 0) ? Number(i.price) : null })) : []; } catch (e) { return []; } };
  const loadD = () => { try { const d = parseFloat(localStorage.getItem(KEYD)); return isFinite(d) ? Math.min(100, Math.max(0, d)) : 0; } catch (e) { return 0; } };
  let items = load(), discount = loadD(), savedQuoteId = '';
  const save = () => { try { localStorage.setItem(KEY, JSON.stringify(items)); localStorage.setItem(KEYD, String(discount)); } catch (e) {} };
  const $ = id => document.getElementById(id);
  const total = () => items.reduce((s, i) => s + i.qty, 0);
  const r2 = n => Math.round(n * 100) / 100;
  const R = n => n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const fmtPct = n => String(n).replace('.', ',');
  // Aceita "12,50", "1.500,00", "1500.5", "R$ 8,9"
  const parseBR = s => {
    s = String(s || '').replace(/[^\d,.]/g, ''); if (!s) return null;
    if (s.indexOf(',') > -1) s = s.replace(/\./g, '').replace(',', '.'); else if (/^\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
    const n = parseFloat(s); return isFinite(n) && n >= 0 ? n : null;
  };
  function calc() {
    let sub = 0, priced = 0;
    items.forEach(i => { if (i.price != null) { sub += r2(i.price * i.qty); priced++; } });
    sub = r2(sub); const disc = r2(sub * discount / 100);
    return { sub, disc, total: r2(sub - disc), priced, missing: items.length - priced };
  }

  const root = document.createElement('div');
  root.innerHTML = '<button type="button" class="quote-fab" id="quoteFab" aria-label="Abrir lista de orçamento">📋 Orçamento <b id="quoteCount">0</b></button>' +
    '<div class="quote-overlay" id="quoteOverlay"></div>' +
    '<aside class="quote-drawer" id="quoteDrawer" aria-hidden="true"><header><h3>Lista de orçamento</h3><button type="button" id="quoteClose" aria-label="Fechar">×</button></header>' +
    '<div class="quote-body" id="quoteBody"></div>' +
    '<div class="quote-foot"><div class="quote-prof"><h4>Dados do cliente</h4><label class="wide">Nome / Razão social<input id="qCustomerName" maxlength="160" placeholder="Nome do cliente"></label><label>CPF / CNPJ<input id="qDocument" maxlength="30"></label><label>WhatsApp<input id="qPhone" maxlength="30"></label><label class="wide">E-mail<input id="qEmail" maxlength="120"></label><label>Veículo / modelo<input id="qVehicle" maxlength="120"></label><label>Placa<input id="qPlate" maxlength="20"></label><h4>Condições comerciais</h4><label>Status<select id="qStatus"><option value="rascunho">Rascunho</option><option value="enviado">Enviado</option><option value="negociacao">Em negociação</option><option value="aprovado">Aprovado</option><option value="perdido">Perdido</option><option value="cancelado">Cancelado</option></select></label><label>Validade (dias)<input id="qValidity" type="number" min="1" max="365" value="7"></label><label class="wide">Condição de pagamento<input id="qPayment" maxlength="160" placeholder="Ex.: 28 dias / PIX / boleto"></label><label class="wide">Retornar ao cliente em<input id="qReturnDate" type="date"></label><label class="wide">Observações<textarea id="quoteNote" maxlength="500" placeholder="Observações da proposta"></textarea></label></div>' +
    '<div id="quotePricing" style="display:none"><label class="quote-disc">Desconto (%)<input id="quoteDisc" inputmode="decimal" maxlength="6" placeholder="0"></label>' +
    '<div class="quote-totals"><div id="quoteSubLine"><span>Subtotal (sem desconto)</span><b id="quoteSub"></b></div><div id="quoteDiscLine"><span id="quoteDiscLbl"></span><b id="quoteDiscVal"></b></div>' +
    '<div class="final"><span id="quoteTotalLbl">Total</span><b id="quoteTotal"></b></div><small id="quoteMissing"></small></div></div>' +
    '<a class="quote-send" id="quoteSend" target="_blank" rel="noopener">Enviar pelo WhatsApp</a>' +
    (window.QUOTE_LOGGED_IN ? '<button type="button" class="quote-save" id="quoteSave">💾 Salvar orçamento</button><small class="quote-save-status" id="quoteSaveStatus"></small>' : '<a class="quote-login" href="admin.php">🔐 Entrar para salvar e continuar depois</a>') +
    '<button type="button" class="quote-pdf" id="quotePdf">📄 Gerar orçamento em PDF</button><button type="button" class="quote-clear" id="quoteClear">Limpar lista</button></div></aside>';
  document.body.appendChild(root);

  function mk(tag, cls, text) { const el = document.createElement(tag); if (cls) el.className = cls; if (text != null) el.textContent = text; return el; }
  function btn(text, fn, cls) { const b = mk('button', cls, text); b.type = 'button'; b.onclick = fn; return b; }
  function message() {
    const note = $('quoteNote').value.trim(), c = calc(), customer = $('qCustomerName') ? $('qCustomerName').value.trim() : '', vehicle = $('qVehicle') ? $('qVehicle').value.trim() : '';
    let t = customer ? 'Olá, '+customer+'! Segue a relação do seu orçamento'+(vehicle?' para '+vehicle:'')+':\n\n' : 'Olá! Segue a relação do orçamento:\n\n';
    items.forEach((it, i) => {
      t += (i + 1) + '. ' + it.code + ' - ' + it.name + ' (Qtd: ' + it.qty + (it.price != null ? ' × ' + R(it.price) + ' = ' + R(r2(it.price * it.qty)) : '') + ')\n';
    });
    if (c.priced) t += '\n' + (discount > 0 ? 'Subtotal: ' + R(c.sub) + '\nDesconto (' + fmtPct(discount) + '%): − ' + R(c.disc) + '\nTotal com desconto: ' + R(c.total) : 'Total: ' + R(c.total)) + '\n';
    return t + (note ? '\n' + note : '');
  }
  function updateSend() {
    const a = $('quoteSend');
    a.href = 'https://wa.me/' + (window.DEFAULT_WHATSAPP || '') + '?text=' + encodeURIComponent(message());
    a.classList.toggle('disabled', !items.length);
    $('quotePdf').disabled = !items.length;
  }
  function updateTotals() {
    const c = calc(), d = discount > 0;
    $('quotePricing').style.display = c.priced ? 'block' : 'none';
    $('quoteSubLine').style.display = d ? 'flex' : 'none';
    $('quoteDiscLine').style.display = d ? 'flex' : 'none';
    $('quoteSub').textContent = R(c.sub);
    $('quoteDiscLbl').textContent = 'Desconto (' + fmtPct(discount) + '%)';
    $('quoteDiscVal').textContent = '− ' + R(c.disc);
    $('quoteTotalLbl').textContent = d ? 'Total com desconto' : 'Total';
    $('quoteTotal').textContent = R(c.total);
    $('quoteMissing').textContent = c.missing ? c.missing + ' item(ns) sem valor não entram no total.' : '';
    updateSend();
  }
  function setQty(code, v) { const it = items.find(i => i.code === code); if (!it) return; it.qty = Math.max(1, Math.min(999, parseInt(v, 10) || 1)); save(); render(); }
  function remove(code) { items = items.filter(i => i.code !== code); save(); render(); }

  function render() {
    $('quoteCount').textContent = total();
    $('quoteFab').style.display = items.length ? 'flex' : 'none';
    const body = $('quoteBody'); body.textContent = '';
    if (!items.length) body.appendChild(mk('p', 'quote-empty', 'Sua lista está vazia. Use "Adicionar ao orçamento" nos produtos.'));
    items.forEach(it => {
      const row = mk('div', 'quote-item'), info = mk('div', 'quote-info');
      info.append(mk('strong', '', it.code), mk('span', '', it.name));
      const qty = mk('div', 'quote-qty'), inp = mk('input'); inp.type = 'number'; inp.min = 1; inp.max = 999; inp.value = it.qty; inp.onchange = () => setQty(it.code, inp.value);
      qty.append(btn('−', () => setQty(it.code, it.qty - 1)), inp, btn('+', () => setQty(it.code, it.qty + 1)));
      const pr = mk('div', 'quote-price'), pin = mk('input'), sub = mk('span', 'sub');
      pin.type = 'text'; pin.inputMode = 'decimal'; pin.placeholder = '0,00'; pin.setAttribute('aria-label', 'Valor unitário de ' + it.code);
      const fmtIn = () => it.price != null ? it.price.toFixed(2).replace('.', ',') : ''; pin.value = fmtIn(); pin.onblur = () => { pin.value = fmtIn(); };
      const setSub = () => { sub.textContent = it.price != null ? R(r2(it.price * it.qty)) : ''; }; setSub();
      pin.oninput = () => { it.price = parseBR(pin.value); save(); setSub(); updateTotals(); };
      pr.append(mk('span', '', 'Valor unit. R$'), pin, sub);
      row.append(info, qty, btn('×', () => remove(it.code), 'quote-rm'), pr); body.appendChild(row);
    });
    updateTotals();
  }
  const syncDisc = () => { $('quoteDisc').value = discount ? fmtPct(discount) : ''; };
  const open = () => { $('quoteDrawer').classList.add('open'); $('quoteOverlay').classList.add('open'); $('quoteDrawer').setAttribute('aria-hidden', 'false'); };
  const close = () => { $('quoteDrawer').classList.remove('open'); $('quoteOverlay').classList.remove('open'); $('quoteDrawer').setAttribute('aria-hidden', 'true'); };

  function esc(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
  // Folha A4 pronta para impressão (logo + data). O cliente escolhe "Salvar como PDF".
  function quoteHtml() {
    const now = new Date(), pad = n => String(n).padStart(2, '0'), c = calc(), withPrice = c.priced > 0;
    const date = now.toLocaleDateString('pt-BR');
    const num = 'ORC-' + now.getFullYear() + pad(now.getMonth() + 1) + pad(now.getDate()) + '-' + pad(now.getHours()) + pad(now.getMinutes());
    const note = $('quoteNote').value.trim();
    const customerName = $('qCustomerName') ? $('qCustomerName').value.trim() : '';
    const vehicle = $('qVehicle') ? $('qVehicle').value.trim() : '';
    const plate = $('qPlate') ? $('qPlate').value.trim().toUpperCase() : '';
    const customerLine = (customerName || vehicle || plate)
      ? '<div class="cli">' +
          '<b>Cliente:</b> ' + esc(customerName || '—') +
          ' &nbsp;|&nbsp; <b>Veículo:</b> ' + esc(vehicle || '—') +
          ' &nbsp;|&nbsp; <b>Placa:</b> ' + esc(plate || '—') +
        '</div>'
      : (note ? '<div class="cli"><b>Cliente / veículo:</b> ' + esc(note) + '</div>' : '<div style="height:7mm"></div>');
    const logo = new URL('assets/eleds-logo.png', location.href).href;
    const rows = items.map((it, i) => '<tr><td class="n">' + (i + 1) + '</td><td class="c">' + esc(it.code) + '</td><td>' + esc(it.name) + '</td><td class="q">' + it.qty + '</td>' +
      (withPrice ? '<td class="p">' + (it.price != null ? R(it.price) : '—') + '</td><td class="p"><b>' + (it.price != null ? R(r2(it.price * it.qty)) : '—') + '</b></td>' : '') + '</tr>').join('');
    const wa = window.DEFAULT_WHATSAPP_DISPLAY ? ' · WhatsApp: ' + esc(window.DEFAULT_WHATSAPP_DISPLAY) : '';
    const tot = !withPrice ? '' : '<div class="tot">' + (discount > 0 ? '<div><span>Subtotal (sem desconto)</span><b>' + R(c.sub) + '</b></div><div><span>Desconto (' + fmtPct(discount) + '%)</span><b>− ' + R(c.disc) + '</b></div>' : '') +
      '<div class="fin"><span>' + (discount > 0 ? 'Total com desconto' : 'Total') + '</span><b>' + R(c.total) + '</b></div>' + (c.missing ? '<small>* ' + c.missing + ' item(ns) sem valor informado não entram no total.</small>' : '') + '</div>';
    return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Orcamento_HE_' + num + '</title><style>' +
      '@page{size:A4;margin:12mm 14mm}*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;color:#182433;-webkit-print-color-adjust:exact;print-color-adjust:exact}' +
      '.top{background:#061522;color:#fff;border-radius:6px;padding:6mm 8mm;display:flex;align-items:center;justify-content:space-between;border-bottom:2mm solid #0879e8}.top img{height:22mm}' +
      '.t{text-align:right}.t h1{margin:0;font-size:22pt;letter-spacing:2px}.t p{margin:4px 0 0;font-size:10pt}' +
      '.cli{border:1px solid #dfe6ed;border-radius:4px;padding:8px 10px;margin:7mm 0;font-size:10pt}' +
      'table{width:100%;border-collapse:collapse;font-size:10pt}thead{display:table-header-group}th{background:#eef3f8;text-align:left;padding:7px 8px;border-bottom:2px solid #0879e8;font-size:9pt}td{padding:7px 8px;border-bottom:1px solid #e1e7ef;vertical-align:top}tr{page-break-inside:avoid}' +
      '.n{width:9mm;color:#687789}.c{width:32mm;font-weight:bold;white-space:nowrap}th.q,.q{width:14mm;text-align:center}.q{font-weight:bold}th.p,.p{width:27mm;text-align:right;white-space:nowrap}' +
      '.sum{margin-top:5mm;text-align:right;font-size:9pt;color:#687789}.tot{margin:5mm 0 0 auto;width:86mm;font-size:10pt;page-break-inside:avoid}.tot div{display:flex;justify-content:space-between;padding:4px 0}.tot .fin{border-top:2px solid #0879e8;margin-top:3px;padding-top:7px;font-size:13pt}.tot small{display:block;color:#687789;font-size:8pt;text-align:right}' +
      '.foot{margin-top:10mm;padding-top:4mm;border-top:1px solid #dfe6ed;font-size:9pt;color:#687789}' +
      '.noprint{position:fixed;top:10px;right:10px;background:#061522;color:#fff;padding:10px 12px;border-radius:8px;font-size:12px}.noprint button{margin-left:8px;padding:6px 10px;border:0;border-radius:5px;background:#0879e8;color:#fff;font-weight:bold;cursor:pointer}@media print{.noprint{display:none}}' +
      '</style></head><body>' +
      '<div class="noprint">Na janela de impressão, escolha “Salvar como PDF”.<button onclick="window.print()">Imprimir / Salvar PDF</button></div>' +
      '<div class="top"><img id="logo" src="' + logo + '" alt="ELEDS Linha Leve e Pesada"><div class="t"><h1>ORÇAMENTO</h1><p>Data: ' + date + '</p><p>Nº ' + num + '</p></div></div>' +
      customerLine +
      '<table><thead><tr><th>#</th><th>Código</th><th>Descrição</th><th class="q">Qtd</th>' + (withPrice ? '<th class="p">Valor unit.</th><th class="p">Subtotal</th>' : '') + '</tr></thead><tbody>' + rows + '</tbody></table>' +
      '<div class="sum">' + items.length + ' item(ns) · ' + total() + ' unidade(s)</div>' + tot +
      '<div class="foot">ELEDS Linha Leve e Pesada — Catálogo Digital 2026/2027' + wa + '</div>' +
      '<script>var i=document.getElementById("logo");function go(){setTimeout(function(){window.print()},300)}if(i.complete)go();else{i.onload=go;i.onerror=go}</script></body></html>';
  }
  function generatePdf() {
    if (!items.length) return;
    const w = window.open('', '_blank');
    if (!w) { alert('Permita pop-ups neste site para gerar o PDF.'); return; }
    w.document.open(); w.document.write(quoteHtml()); w.document.close();
  }

  window.addQuote = function (code, name, el) {
    code = String(code || ''); if (!code) return;
    const f = items.find(i => i.code === code);
    if (f) f.qty = Math.min(999, f.qty + 1); else if (items.length < 50) items.push({ code, name: String(name || ''), qty: 1, price: null }); else { alert('A lista aceita até 50 itens diferentes. Envie esta e faça outra.'); return; }
    save(); render();
    if (el) { if (!el._orig) el._orig = el.textContent; el.textContent = '✓ Adicionado'; clearTimeout(el._t); el._t = setTimeout(() => { el.textContent = el._orig; }, 1200); }
  };


  async function saveServerQuote() {
    if (!window.QUOTE_LOGGED_IN || !items.length) return;
    const b = $('quoteSave'), st = $('quoteSaveStatus'); b.disabled = true; st.textContent = 'Salvando...';
    try {
      const r = await fetch('orcamento_api.php?action=save', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:savedQuoteId,items,discount,note:$('quoteNote').value.trim(),status:$('qStatus').value,customer:{name:$('qCustomerName').value.trim(),document:$('qDocument').value.trim(),phone:$('qPhone').value.trim(),email:$('qEmail').value.trim(),vehicle:$('qVehicle').value.trim(),plate:$('qPlate').value.trim()},commercial:{validity:$('qValidity').value,payment:$('qPayment').value.trim(),return_date:$('qReturnDate').value}})});
      const j = await r.json(); if (!r.ok || !j.ok) throw new Error(j.error || 'Falha ao salvar');
      savedQuoteId = j.id; st.textContent = '✓ Orçamento salvo. Você pode continuar depois em Meus orçamentos.'; b.textContent = '💾 Atualizar orçamento';
    } catch(e) { st.textContent = e.message || 'Não foi possível salvar.'; } finally { b.disabled = false; }
  }
  async function loadServerQuote() {
    if (!window.QUOTE_LOGGED_IN || !window.LOAD_QUOTE_ID) return;
    try {
      const r=await fetch('orcamento_api.php?action=load&id='+encodeURIComponent(window.LOAD_QUOTE_ID)); const j=await r.json(); if(!r.ok||!j.ok) throw new Error(j.error||'Falha ao abrir');
      const q=j.quote; items=Array.isArray(q.items)?q.items:[]; discount=Number(q.discount)||0; savedQuoteId=q.id||''; save(); syncDisc(); render(); $('quoteNote').value=q.note||''; $('qStatus').value=q.status||'rascunho'; const c=q.customer||{}, cm=q.commercial||{}; $('qCustomerName').value=c.name||''; $('qDocument').value=c.document||''; $('qPhone').value=c.phone||''; $('qEmail').value=c.email||''; $('qVehicle').value=c.vehicle||''; $('qPlate').value=c.plate||''; $('qValidity').value=cm.validity||7; $('qPayment').value=cm.payment||''; $('qReturnDate').value=cm.return_date||''; updateSend(); open();
      if($('quoteSave')) $('quoteSave').textContent='💾 Atualizar orçamento';
    } catch(e) { alert(e.message||'Não foi possível abrir o orçamento.'); }
  }

  $('quoteFab').onclick = open; $('quoteClose').onclick = close; $('quoteOverlay').onclick = close;
  $('quotePdf').onclick = generatePdf;
  if ($('quoteSave')) $('quoteSave').onclick = saveServerQuote;
  $('quoteClear').onclick = () => { if (items.length && confirm('Limpar toda a lista de orçamento?')) { items = []; discount = 0; save(); syncDisc(); render(); } };
  $('quoteDisc').addEventListener('input', () => { const v = parseBR($('quoteDisc').value); discount = v == null ? 0 : Math.min(100, v); save(); updateTotals(); });
  $('quoteNote').addEventListener('input', updateSend);
  $('quoteSend').addEventListener('click', e => { if (!items.length) e.preventDefault(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
  window.addEventListener('storage', e => { if (e.key === KEY || e.key === KEYD) { items = load(); discount = loadD(); syncDisc(); render(); } });
  document.addEventListener('click', e => { const b = e.target.closest('#modalQuoteBtn'); const p = window.currentModalProduct; if (b && p) window.addQuote(p.code, p.name, b); });
  syncDisc(); render(); loadServerQuote();
})();
