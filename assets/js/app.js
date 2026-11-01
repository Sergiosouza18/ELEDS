function openProduct(product) {
    window.currentModalProduct = product;
    const modal = document.getElementById('productModal');
    document.getElementById('modalCategory').textContent = product.category || '';
    document.getElementById('modalName').textContent = product.name || '';
    document.getElementById('modalCode').textContent = product.code || '';
    document.getElementById('modalBrand').textContent = product.brand || 'HE';
    const app = document.getElementById('modalApplication'), appWrap = document.getElementById('modalApplicationWrap');
    app.textContent = product.application || ''; appWrap.style.display = product.application ? 'block' : 'none';
    const measures = document.getElementById('modalMeasures'), measuresWrap = document.getElementById('modalMeasuresWrap');
    if (measures && measuresWrap) { measures.textContent = product.measures || ''; measuresWrap.style.display = product.measures ? 'block' : 'none'; }
    const images = Array.isArray(product.images) && product.images.length ? product.images : (product.image ? [product.image] : []);
    const modalImage=document.getElementById('modalImage'), fallback=document.getElementById('modalImageFallback'), gallery=document.getElementById('modalGallery');
    gallery.innerHTML='';
    if(images.length){ modalImage.src=images[0]; modalImage.alt=product.name||''; modalImage.style.display='block'; modalImage.style.cursor='zoom-in'; fallback.style.display='none';
      images.forEach((src,i)=>{const b=document.createElement('button'); b.type='button'; b.className='modal-thumb'+(i===0?' active':''); const im=document.createElement('img'); im.src=src; im.alt=''; b.appendChild(im); b.onclick=()=>{modalImage.src=src; gallery.querySelectorAll('.modal-thumb').forEach(x=>x.classList.remove('active')); b.classList.add('active');}; gallery.appendChild(b);});
    } else { modalImage.removeAttribute('src'); modalImage.style.display='none'; fallback.style.display='block'; }
    const message=`Olá! Gostaria de um orçamento do produto ${product.code} - ${product.name}.`;
    document.getElementById('modalWhatsapp').href='https://wa.me/'+(window.DEFAULT_WHATSAPP||'')+'?text='+encodeURIComponent(message);
    modal.classList.add('show'); modal.setAttribute('aria-hidden','false'); document.body.classList.add('modal-open');
}

let zoomScale = 1;
function openImageZoom(){
    const modalImage=document.getElementById('modalImage');
    const zoom=document.getElementById('imageZoom');
    const zoomImage=document.getElementById('zoomImage');
    if(!modalImage || !modalImage.src || modalImage.style.display==='none') return;
    zoomImage.src=modalImage.src;
    zoomImage.alt=modalImage.alt || '';
    zoomScale=1;
    updateZoom();
    zoom.classList.add('show');
    zoom.setAttribute('aria-hidden','false');
}
function closeImageZoom(){
    const zoom=document.getElementById('imageZoom');
    if(!zoom) return;
    zoom.classList.remove('show');
    zoom.setAttribute('aria-hidden','true');
}
function updateZoom(){
    const img=document.getElementById('zoomImage');
    const label=document.getElementById('zoomPercent');
    if(img) img.style.transform=`scale(${zoomScale})`;
    if(label) label.textContent=Math.round(zoomScale*100)+'%';
}
function zoomIn(){ zoomScale=Math.min(4, zoomScale+0.25); updateZoom(); }
function zoomOut(){ zoomScale=Math.max(1, zoomScale-0.25); updateZoom(); }
function resetZoom(){ zoomScale=1; updateZoom(); }

function closeProduct(){ const modal=document.getElementById('productModal'); if(!modal)return; modal.classList.remove('show'); modal.setAttribute('aria-hidden','true'); document.body.classList.remove('modal-open'); }
document.addEventListener('keydown',e=>{if(e.key==='Escape'){ const z=document.getElementById('imageZoom'); if(z?.classList.contains('show')) closeImageZoom(); else closeProduct(); }});

document.getElementById('modalImage')?.addEventListener('click',openImageZoom);
document.getElementById('imageZoom')?.addEventListener('click',e=>{if(e.target===e.currentTarget)closeImageZoom();});
document.getElementById('zoomImage')?.addEventListener('wheel',e=>{e.preventDefault(); if(e.deltaY<0)zoomIn(); else zoomOut();},{passive:false});
document.getElementById('productModal')?.addEventListener('click',e=>{if(e.target===e.currentTarget)closeProduct();});
const menu=document.querySelector('.menu-toggle'), nav=document.getElementById('mainNav');
menu?.addEventListener('click',()=>{const open=nav.classList.toggle('open');menu.setAttribute('aria-expanded',open?'true':'false');});
nav?.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>nav.classList.remove('open')));
