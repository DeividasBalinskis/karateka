// Budo taisyklės: rungtys, sistemos (olimpinė, ratų, balai, fukugo), medalių variantai, grupės nustatymai, kovos būdas.
// Variklio dalis: be naršyklės (tests/engine).
export var DISC = { kata: 'Kata', kumite: 'Kumite', fukugo: 'Fukugo', kihon: 'Kihon', enbu: 'Enbu', sumo: 'Sumo' };
// Sistemos (kaip Kihapp): olimpinės lentelės, fukugo, ratų sistema, balai
export var SYSTEMS = {
  wkf: ['Olimpinė + paguoda', 'Pralaimėję finalininkams kovoja paguodoje, 2 bronzos (WKF).'],
  bronze: ['Olimpinė + kova dėl 3 vietos', 'Viena trečia vieta: pusfinalių pralaimėtojai kovoja dėl bronzos.'],
  plain: ['Olimpinė, 2 bronzos', 'Dvi trečios vietos: abu pusfinalių pralaimėtojai - be kovos.'],
  fukugo: ['Fukugo', 'Finalas - kumite, pusfinalis - kitei (vėliavėlės), toliau pakaitomis. 2 bronzos.'],
  rr: ['Ratų sistema', 'Visi su visais. Kovoje - kiek vėliavėlių prieš kiek (ar kumite balai).'],
  score: ['Balai', 'Teisėjai vertina balais, kraštiniai atmetami (matosi perbraukti). Enbu, kata.']
};
export function isElim(sys) { return sys !== 'rr' && sys !== 'score'; }
// Sistemos pavadinimas pagal rungtį (Kata vėliavėlėmis, Kata balais, Enbu balais, Fukugo...); medalių variantas - atskiras nustatymas
export var MEDALS = { plain: '2 bronzos be kovos', bronze: 'Kova dėl 3 vietos', wkf: 'Paguoda, 2 bronzos (WKF)' };
export function methodOf(sys) { return sys === 'score' || sys === 'rr' || sys === 'fukugo' ? sys : 'elim'; }
export function discName(c) {
  var d = DISC[c.discipline] || c.discipline;
  if (!c.team || c.discipline === 'enbu') return d;
  return (c.discipline === 'kumite' || c.discipline === 'kihon' ? 'Komandinis ' : 'Komandinė ') + d.toLowerCase();
}
export function methodList(c) {
  var d = c.discipline, D = discName(c);
  if (d === 'fukugo') return [['fukugo', 'Fukugo', 'Kumite ir kitei (vėliavėlės) pakaitomis, finalas - kumite.'], ['rr', 'Fukugo - ratų sistema', 'Visi su visais.']];
  if (d === 'kumite' || d === 'sumo') return [['elim', D + ' - olimpinė', 'Lentelė, kovos su balais ir laiku.'], ['rr', D + ' - ratų sistema', 'Visi su visais.']];
  return [['elim', D + ' vėliavėlėmis', 'Lentelė: laimėtoją teisėjai rodo vėliavėlėmis.'], ['score', D + ' balais', 'Teisėjai vertina balais, kraštiniai atmetami.'], ['rr', D + ' - ratų sistema', 'Visi su visais (vėliavėlės).']];
}
export function sysName(c, sys) {
  var m = methodOf(sys), it = methodList(c).filter(function (x) { return x[0] === m; })[0];
  return (it ? it[1] : SYSTEMS[sys][0]) + (m === 'elim' ? ' · ' + MEDALS[sys] : '');
}
export function elimDefault(c) { var f = cfg(c); return MEDALS[f.medals] ? f.medals : (c.team ? 'bronze' : 'plain'); }
export function methodToSys(c, m) { return m === 'elim' ? elimDefault(c) : m; }
// Numatyta sistema pagal rungtį (pasirinkus rungtį - nustatoma pati, kol kovų dar nebuvo)
export function defaultSystem(c) {
  if (c.discipline === 'enbu') return 'score';
  if (c.discipline === 'fukugo') return 'fukugo';
  return c.team ? 'bronze' : 'plain';
}
export function cfg(c) {
  c.cfg = c.cfg || {}; var f = c.cfg; f.judges = f.judges || 5; f.rounds = f.rounds || 1; f.finalists = f.finalists || 8; if (f.drop === undefined) f.drop = true;
  f.time = f.time || (c.discipline === 'sumo' ? 60 : 120); f.sumoTo = f.sumoTo || 3; if (f.bunkaiFinal === undefined) f.bunkaiFinal = false;
  f.avg = false;   // balų sistemoje: rato balas - teisėjų balų suma (be kraštinių); vidurkio nebėra (jis apvalindavo)
  return f;
}
// Kaip vyksta kova: kumite (balai +/- ir laikrodis), komandinis kumite (3 kovos, balai sumuojasi), sumo (iki N taškų),
// vėliavėlės (kata, kihon, kitei), komandinės katos finalas (Kata + Pritaikymas balais)
export function boutMode(c, m) {
  var d = c.discipline, sys = c.draw ? c.draw.system : defaultSystem(c);
  if (d === 'sumo') return 'sumo';
  if (sys === 'fukugo' || d === 'fukugo') return m.left && m.left % 2 === 0 ? 'flags' : 'kumite';
  if (d === 'kumite') return c.team ? 'team' : 'kumite';
  if (c.team && cfg(c).bunkaiFinal && (m.left === 1 || m.id === 'bronze') && isElim(sys)) return 'score2';   // tik jei įjungta nustatymuose
  return 'flags';
}
export var MODE_NAME = { kumite: 'Kumite', team: 'Komandinis kumite', sumo: 'Sumo', flags: 'Vėliavėlės', score2: 'Kata + Pritaikymas' };
export function person(c, id) { return c.competitors.find(function (p) { return p.id === id; }) || null; }
