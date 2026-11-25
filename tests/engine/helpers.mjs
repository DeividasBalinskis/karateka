// Budo variklio testų pagalba: grupė (kategorija) su dalyviais ir burtais - be naršyklės ir be serverio
export const ENGINE = new URL('../../public_html/assets/varzybos/', import.meta.url);

// grupe(['a', 'b'], { clubs: { a: 'X' }, system: 'plain', slots: [...] }) - dalyvių id = vardai
export function grupe(ids, o = {}) {
  const c = {
    id: o.id || 'k1', name: 'Testinė grupė', discipline: o.discipline || 'kata', team: !!o.team, cfg: o.cfg || {},
    competitors: ids.map((id) => ({ id, name: id.toUpperCase(), club: (o.clubs && o.clubs[id]) || 'Klubas ' + id })),
  };
  if (o.system) {
    c.draw = o.slots
      ? { system: o.system, slots: o.slots, results: o.results || {}, scores: o.scores || {}, locked: false }
      : { system: o.system, order: o.order || ids.slice(), results: o.results || {}, scores: o.scores || {}, final: o.final || {}, locked: false };
  }
  return c;
}
