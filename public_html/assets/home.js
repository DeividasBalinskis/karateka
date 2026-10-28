// Pagrindinis puslapis: skaidrės, atsiradimas slenkant, grupių langas, „Išbandyti“ forma
// „Apie“ nuotraukų skaidrės keičiasi pačios
document.querySelectorAll('[data-slider]').forEach(function (box) {
  var slides = box.querySelectorAll('.slide'), i = 0;
  if (slides.length > 1) setInterval(function () { slides[i].classList.remove('active'); i = (i + 1) % slides.length; slides[i].classList.add('active'); }, 4000);
});

// Elementas tolygiai atsiranda, kai pasiekiamas slenkant, ir toliau lieka matomas
const io = new IntersectionObserver((entries)=>{
  entries.forEach(e=>{
    if (e.isIntersecting) { e.target.classList.add('in-view'); io.unobserve(e.target); }
  });
}, {threshold:0.12});
document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

// Grupių langas: slenkama į šonus (pele, pirštu), mygtukai ir skirtukai - patogumui
const groupScroll = document.getElementById('groupScroll');
if (groupScroll) {
  const groupSlideIds = [].map.call(groupScroll.querySelectorAll('.group-section'), s => s.id);
  const groupDots = document.querySelectorAll('#groupTabs button');
  function scrollToGroup(i){
    const idx = ((i % groupSlideIds.length) + groupSlideIds.length) % groupSlideIds.length;
    groupScroll.scrollTo({left: idx * groupScroll.clientWidth, behavior:'smooth'});
    groupDots.forEach((d, di) => d.classList.toggle('active', di === idx));
  }
  document.getElementById('groupPrev').addEventListener('click', ()=> scrollToGroup(Math.round(groupScroll.scrollLeft / groupScroll.clientWidth) - 1));
  document.getElementById('groupNext').addEventListener('click', ()=> scrollToGroup(Math.round(groupScroll.scrollLeft / groupScroll.clientWidth) + 1));
  groupDots.forEach(d => d.addEventListener('click', ()=> scrollToGroup(parseInt(d.dataset.i))));
  let scrollTimeout;
  groupScroll.addEventListener('scroll', ()=>{
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(()=>{
      const idx = Math.round(groupScroll.scrollLeft / groupScroll.clientWidth);
      groupDots.forEach((d, i) => d.classList.toggle('active', i === idx));
    }, 80);
  }, {passive:true});
  // Atidaro grupę pagal #vaikams / #jaunimui / #suaugusiems. Grupių sekcija pačiame viršuje, todėl slenkame į puslapio pradžią.
  function openGroup(id, smooth){
    const idx = groupSlideIds.indexOf(id);
    if(idx < 0) return false;
    // Karuselę perjungiame iškart, o puslapį į viršų slenkame atskirai (du lygiagretūs "smooth" slinkimai vienas kitą nutraukia)
    groupScroll.style.scrollBehavior = 'auto';
    groupScroll.scrollLeft = idx * groupScroll.clientWidth;
    groupScroll.style.scrollBehavior = '';
    window.scrollTo({top:0, behavior: smooth ? 'smooth' : 'instant'});
    groupDots.forEach((d, i) => d.classList.toggle('active', i === idx));
    return true;
  }
  function openGroupFromHash(){
    const id = window.location.hash.replace('#','');
    if(id === 'grupes'){ window.scrollTo({top:0, behavior:'instant'}); return; }
    openGroup(id, false);
  }
  // Meniu "Treniruotės" - į puslapio viršų, kur "Kam ieškote treniruočių?"
  document.querySelectorAll('a[href="#grupes"]').forEach(a => a.addEventListener('click', (e) => {
    e.preventDefault();
    history.replaceState(null, '', '#grupes');
    window.scrollTo({top:0, behavior:'smooth'});
  }));
  window.addEventListener('load', openGroupFromHash);
  window.addEventListener('hashchange', openGroupFromHash);
  // Meniu nuorodos į grupes veikia ir tada, kai adreso #... nesikeičia (pvz. du kartus iš eilės ta pati grupė)
  document.querySelectorAll('a[href="#vaikams"], a[href="#jaunimui"], a[href="#suaugusiems"]').forEach(a => {
    a.addEventListener('click', (e) => {
      const id = a.getAttribute('href').slice(1);
      if(openGroup(id, true)){ e.preventDefault(); history.replaceState(null, '', '#' + id); }
    });
  });
}

// Vietų sąrašas: atidarius vieną grupę, kitos užsidaro (sąrašas gali būti atnaujintas redaguojant - todėl per document)
document.addEventListener('toggle', (ev) => {
  const d = ev.target;
  if (!d.matches || !d.matches('.loc-accordion details.loc-group') || !d.open) return;
  d.closest('.loc-accordion').querySelectorAll('details.loc-group').forEach(other => { if (other !== d) other.open = false; });
}, true);

// Vaikams: paspaudus vietą, jos tvarkaraštis rodomas dešinėje
document.addEventListener('click', (ev) => {
  const li = ev.target.closest('.picker-locations li[data-schedule]');
  const panel = document.getElementById('vaikamsSchedulePanel');
  if (!li || !panel) return;
  document.querySelectorAll('.picker-locations li[data-schedule]').forEach(other => other.classList.remove('active'));
  li.classList.add('active');
  panel.innerHTML = '<div class="kicker">Tvarkaraštis</div><div class="schedule-name"></div>' + li.dataset.schedule;
  panel.querySelector('.schedule-name').textContent = li.dataset.name;
});

// Gimimo metai - užpildoma čia, kad intervalas būtų vienoje vietoje
const yearSelect = document.getElementById('gimimoMetai');
if (yearSelect) {
  const YEAR_NEWEST = new Date().getFullYear();
  const YEAR_OLDEST = 1940;
  for (let y = YEAR_NEWEST; y >= YEAR_OLDEST; y--) {
    const opt = document.createElement('option');
    opt.value = y;
    opt.textContent = y;
    yearSelect.appendChild(opt);
  }
  yearSelect.addEventListener('change', () => {
    yearSelect.classList.toggle('is-empty', yearSelect.value === '');
  });
}

// Registracijos forma -> uzklausa.php; nepavykus - aiški klaida
const regForm = document.getElementById('registrationForm');
const formStatus = document.getElementById('formStatus');
if (regForm && !document.body.classList.contains('home-edit')) regForm.addEventListener('submit', async (e)=>{
  e.preventDefault();
  formStatus.textContent = 'Siunčiama...';
  formStatus.className = 'form-status';
  const submitBtn = regForm.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  try{
    const res = await fetch('uzklausa.php', {
      method:'POST',
      body:new FormData(regForm)
    });
    const data = await res.json().catch(()=>({ok:false}));
    if(res.ok && data.ok){
      formStatus.textContent = 'Ačiū! Susisieksime artimiausiu metu.';
      formStatus.className = 'form-status ok';
      regForm.reset();
      if (yearSelect) yearSelect.classList.add('is-empty');
    } else {
      throw new Error('send failed');
    }
  } catch(err){
    formStatus.textContent = 'Nepavyko išsiųsti. Parašykite mums tiesiogiai: info@karateka.lt';
    formStatus.className = 'form-status err';
  } finally {
    submitBtn.disabled = false;
  }
});
