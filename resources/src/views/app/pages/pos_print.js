// Printing for the POS: 80 mm thermal ticket and A5 invoice.
// This shop does not use VAT (no tax lines) and the customer's phone number is never printed.
// The page is built as plain HTML and printed from a hidden iframe, so it works the same
// in every browser and does not depend on the app's CSS.
import JsBarcode from "jsbarcode";

const esc = value =>
  String(value === null || value === undefined ? "" : value).replace(
    /[&<>"']/g,
    c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])
  );

// numbers and Latin names keep their own direction inside Arabic text
const iso = value => "<bdi>" + esc(value) + "</bdi>";

const qty = v => {
  const n = parseFloat(v) || 0;
  return Number.isInteger(n) ? String(n) : n.toFixed(2);
};

function barcodeSvg(value) {
  try {
    const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    JsBarcode(svg, value, { format: "CODE128", width: 1.6, height: 42, displayValue: true, fontSize: 12, margin: 0 });
    return svg.outerHTML;
  } catch (e) {
    return "";
  }
}

const BASE_CSS = `
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; }
  body { color: #111; font-family: -apple-system, "Segoe UI", Tahoma, Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  bdi { unicode-bidi: isolate; }
  .row { display: flex; justify-content: space-between; gap: 8px; }
  .num { text-align: end; white-space: nowrap; }
`;

/**
 * @param {object} data  response of GET pos/sale_print/{id}
 * @param {object} o     { t(key, params), money(value), dir, lang, origin }
 */
function context(data, o) {
  const sale = data.sale;
  const methodLabel = m => {
    const map = { Cash: "method_cash", tpe: "method_tpe", cheque: "method_cheque", "bank transfer": "method_bank_transfer", other: "method_other" };
    return map[m] ? o.t("PosNext." + map[m]) : m;
  };
  const statusLabel = s => {
    const key = "PosNext.status_" + s;
    const label = o.t(key);
    return label === key ? s : label;
  };
  const logo = data.company.logo ? o.origin + "/images/" + encodeURIComponent(data.company.logo) : "";
  const change = (data.payments || []).reduce((sum, p) => sum + (parseFloat(p.change) || 0), 0);
  return { sale, methodLabel, statusLabel, logo, change };
}

export function ticketHtml(data, o) {
  const { sale, methodLabel, logo, change } = context(data, o);
  const ps = data.pos_settings || {};
  const t = o.t;
  // a ticket with something still due always names the customer
  const showCustomer = ps.show_customer || sale.due > 0;
  const c = sale.client;

  const items = data.details
    .map(
      d => `
      <div class="item">
        <div class="n">${esc(d.name)}</div>
        <div class="row"><span>${iso(qty(d.quantity) + " " + d.unit)} × ${iso(o.money(d.price))}</span><span class="num">${iso(o.money(d.total))}</span></div>
        ${d.imei ? `<div class="small">IMEI: ${iso(d.imei)}</div>` : ""}
      </div>`
    )
    .join("");

  const line = (label, value, cls = "") => `<div class="row ${cls}"><span>${esc(label)}</span><span class="num">${value}</span></div>`;

  const payments = (data.payments || [])
    .map(p => line(methodLabel(p.Reglement), iso(o.money(p.montant))))
    .join("");

  return `<!doctype html><html dir="${o.dir}" lang="${esc(o.lang)}"><head><meta charset="utf-8"><title>${esc(sale.Ref)}</title>
<style>${BASE_CSS}
  @page { size: 80mm auto; margin: 0; }
  body { width: 80mm; padding: 4mm 4mm 6mm; font-size: 12px; line-height: 1.35; }
  .c { text-align: center; }
  .logo { max-width: 38mm; max-height: 18mm; margin-bottom: 2px; }
  h1 { font-size: 15px; margin: 2px 0; }
  .small { font-size: 10.5px; color: #333; }
  hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
  .item { margin-bottom: 5px; }
  .item .n { font-weight: 600; }
  .total { font-size: 16px; font-weight: 800; margin: 3px 0; }
  .due { font-weight: 700; }
  .barcode { margin-top: 6px; }
  .barcode svg { max-width: 100%; height: auto; }
</style></head><body>
  <div class="c">
    ${logo ? `<img class="logo" src="${esc(logo)}" alt="" onerror="this.remove()">` : ""}
    <h1>${esc(data.company.name)}</h1>
    ${data.company.address ? `<div class="small">${esc(data.company.address)}</div>` : ""}
    ${data.company.phone ? `<div class="small">${esc(t("PosNext.phone"))}: ${iso(data.company.phone)}</div>` : ""}
  </div>
  <hr>
  ${line(t("PosNext.ref"), iso(sale.Ref))}
  ${line(t("PosNext.date"), iso(sale.date + (sale.time ? " " + sale.time : "")))}
  ${line(t("PosNext.seller"), iso(sale.seller))}
  ${ps.show_warehouse ? line(t("PosNext.warehouse"), esc(sale.warehouse)) : ""}
  ${showCustomer ? line(t("PosNext.customer"), esc(c.name)) : ""}
  ${showCustomer && ps.show_email && c.email ? line(t("PosNext.email"), iso(c.email)) : ""}
  ${showCustomer && ps.show_address && c.address ? line(t("PosNext.address"), esc(c.address)) : ""}
  <hr>
  ${items}
  <hr>
  ${line(t("PosNext.subtotal"), iso(o.money(sale.subtotal)))}
  ${sale.discount > 0 ? line(t("PosNext.discount"), iso("- " + o.money(sale.discount))) : ""}
  ${sale.shipping > 0 ? line(t("PosNext.shipping"), iso(o.money(sale.shipping))) : ""}
  ${line(t("PosNext.total"), iso(o.money(sale.GrandTotal)), "total")}
  ${payments ? "<hr>" + payments : ""}
  ${line(t("PosNext.paid"), iso(o.money(sale.paid_amount)))}
  ${change > 0 ? line(t("PosNext.change"), iso(o.money(change))) : ""}
  ${sale.due > 0 ? line(t("PosNext.due"), iso(o.money(sale.due)), "due") : ""}
  ${ps.show_note && ps.note_customer ? `<hr><div class="c small">${esc(ps.note_customer)}</div>` : ""}
  ${ps.show_barcode ? `<div class="c barcode">${barcodeSvg(sale.Ref)}</div>` : ""}
</body></html>`;
}

export function a5Html(data, o) {
  const { sale, methodLabel, statusLabel, logo, change } = context(data, o);
  const t = o.t;
  const c = sale.client;

  const rows = data.details
    .map(
      (d, i) => `
      <tr>
        <td>${i + 1}</td>
        <td><strong>${esc(d.name)}</strong><div class="muted">${iso(d.code)}${d.imei ? " · IMEI " + iso(d.imei) : ""}</div></td>
        <td class="num">${iso(qty(d.quantity) + " " + d.unit)}</td>
        <td class="num">${iso(o.money(d.price))}</td>
        <td class="num">${iso(o.money(d.total))}</td>
      </tr>`
    )
    .join("");

  const line = (label, value, cls = "") => `<div class="row ${cls}"><span>${esc(label)}</span><span class="num">${value}</span></div>`;

  const payments = (data.payments || [])
    .map(p => `<div class="row"><span>${iso(p.date)} · ${esc(methodLabel(p.Reglement))}</span><span class="num">${iso(o.money(p.montant))}</span></div>`)
    .join("");

  return `<!doctype html><html dir="${o.dir}" lang="${esc(o.lang)}"><head><meta charset="utf-8"><title>${esc(sale.Ref)}</title>
<style>${BASE_CSS}
  @page { size: A5 portrait; margin: 10mm; }
  body { font-size: 11px; line-height: 1.45; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding-bottom: 10px; border-bottom: 2px solid #663399; }
  .company { display: flex; gap: 10px; align-items: center; }
  .company img { max-width: 26mm; max-height: 18mm; }
  .company h1 { font-size: 15px; margin: 0 0 2px; }
  .muted { color: #666; font-size: 10px; }
  .doc { text-align: end; }
  .doc .title { font-size: 20px; font-weight: 800; color: #663399; letter-spacing: .04em; text-transform: uppercase; }
  .boxes { display: flex; gap: 10px; margin-top: 10px; }
  .box { flex: 1; border: 1px solid #e3e3ec; border-radius: 6px; padding: 7px 9px; }
  .box h4 { margin: 0 0 3px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .05em; color: #663399; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  th { background: #f3edfa; color: #3b2160; font-size: 9.5px; text-transform: uppercase; letter-spacing: .03em; padding: 6px; text-align: start; }
  td { padding: 6px; border-bottom: 1px solid #ececf2; vertical-align: top; }
  th.num, td.num { text-align: end; }
  .bottom { display: flex; gap: 14px; margin-top: 10px; align-items: flex-start; }
  .bottom .left { flex: 1; }
  .totals { width: 52%; }
  .totals .row { padding: 2px 0; }
  .grand { font-size: 14px; font-weight: 800; border-top: 2px solid #111; margin-top: 4px; padding-top: 5px; }
  .due { color: #b91c1c; font-weight: 700; }
  .status { display: inline-block; padding: 1px 8px; border-radius: 999px; background: #f3edfa; color: #663399; font-weight: 700; font-size: 10px; }
  .footer { margin-top: 16px; text-align: center; color: #666; font-size: 9.5px; }
</style></head><body>
  <div class="head">
    <div class="company">
      ${logo ? `<img src="${esc(logo)}" alt="" onerror="this.remove()">` : ""}
      <div>
        <h1>${esc(data.company.name)}</h1>
        ${data.company.address ? `<div class="muted">${esc(data.company.address)}</div>` : ""}
        <div class="muted">${data.company.phone ? iso(data.company.phone) : ""}${data.company.phone && data.company.email ? " · " : ""}${data.company.email ? iso(data.company.email) : ""}</div>
      </div>
    </div>
    <div class="doc">
      <div class="title">${esc(t("PosNext.invoice_title"))}</div>
      <div><strong>${iso(sale.Ref)}</strong></div>
      <div class="muted">${iso(sale.date + (sale.time ? " " + sale.time : ""))}</div>
    </div>
  </div>

  <div class="boxes">
    <div class="box">
      <h4>${esc(t("PosNext.bill_to"))}</h4>
      <strong>${esc(c.name)}</strong>
      ${c.email ? `<div class="muted">${iso(c.email)}</div>` : ""}
      ${c.address ? `<div class="muted">${esc(c.address)}</div>` : ""}
    </div>
    <div class="box">
      <h4>${esc(t("PosNext.sale_info"))}</h4>
      <div>${esc(t("PosNext.warehouse"))}: ${esc(sale.warehouse)}</div>
      <div>${esc(t("PosNext.seller"))}: ${iso(sale.seller)}</div>
      <div>${esc(t("PosNext.status"))}: <span class="status">${esc(statusLabel(sale.payment_status))}</span></div>
    </div>
  </div>

  <table>
    <thead><tr>
      <th>#</th><th>${esc(t("PosNext.item"))}</th><th class="num">${esc(t("PosNext.qty"))}</th>
      <th class="num">${esc(t("PosNext.unit_price"))}</th><th class="num">${esc(t("PosNext.total"))}</th>
    </tr></thead>
    <tbody>${rows}</tbody>
  </table>

  <div class="bottom">
    <div class="left">
      ${payments ? `<div class="box"><h4>${esc(t("PosNext.payments"))}</h4>${payments}${change > 0 ? `<div class="row"><span>${esc(t("PosNext.change"))}</span><span class="num">${iso(o.money(change))}</span></div>` : ""}</div>` : ""}
      ${sale.notes ? `<div class="box" style="margin-top:8px"><h4>${esc(t("PosNext.note"))}</h4>${esc(sale.notes)}</div>` : ""}
    </div>
    <div class="totals">
      ${line(t("PosNext.subtotal"), iso(o.money(sale.subtotal)))}
      ${sale.discount > 0 ? line(t("PosNext.discount"), iso("- " + o.money(sale.discount))) : ""}
      ${sale.shipping > 0 ? line(t("PosNext.shipping"), iso(o.money(sale.shipping))) : ""}
      ${line(t("PosNext.total"), iso(o.money(sale.GrandTotal)), "grand")}
      ${line(t("PosNext.paid"), iso(o.money(sale.paid_amount)))}
      ${sale.due > 0 ? line(t("PosNext.due"), iso(o.money(sale.due)), "due") : ""}
    </div>
  </div>

  ${data.company.invoice_footer ? `<div class="footer">${esc(data.company.invoice_footer)}</div>` : ""}
</body></html>`;
}

// List of the products sold in the POS on one day (GET pos/day_products), as an 80 mm ticket or an A4 page
export function dayListHtml(data, o, format) {
  const t = o.t;
  const ticket = format === "ticket";
  const logo = data.company && data.company.logo ? o.origin + "/images/" + encodeURIComponent(data.company.logo) : "";
  const title = t("PosNext.day_list_title");

  const rows = data.lines
    .map(l =>
      ticket
        ? `<div class="item">
            <div class="row"><span class="n">${esc(l.name)}</span><span class="num">${iso(l.time)}</span></div>
            <div class="row"><span>${iso(qty(l.quantity) + " " + l.unit)} × ${iso(o.money(l.price))}</span><span class="num">${iso(o.money(l.total))}</span></div>
          </div>`
        : `<tr>
            <td class="mono">${iso(l.time)}</td>
            <td><strong>${esc(l.name)}</strong><div class="muted">${iso(l.code)} · ${iso(l.Ref)}${l.imei ? " · IMEI " + iso(l.imei) : ""}</div></td>
            <td class="num">${iso(qty(l.quantity) + " " + l.unit)}</td>
            <td class="num">${iso(o.money(l.price))}</td>
            <td class="num">${iso(o.money(l.total))}</td>
          </tr>`
    )
    .join("");

  const charges = (data.charges || [])
    .map(c => `<div class="row"><span>${esc(c.category || "")} ${c.details ? '<span class="muted">' + esc(c.details) + "</span>" : ""}</span><span class="num">${iso("- " + o.money(c.amount))}</span></div>`)
    .join("");

  const totals = `
    <div class="row"><span>${esc(t("PosNext.products"))}</span><span class="num">${iso(data.totals.lines)}</span></div>
    <div class="row"><span>${esc(t("PosNext.qty"))}</span><span class="num">${iso(qty(data.totals.quantity))}</span></div>
    <div class="row grand"><span>${esc(t("PosNext.total"))}</span><span class="num">${iso(o.money(data.totals.amount))}</span></div>
    ${data.totals.charges ? `<div class="row"><span>${esc(t("PosNext.charges_sum"))}</span><span class="num">${iso("- " + o.money(data.totals.charges))}</span></div>
    <div class="row"><span>${esc(t("PosNext.after_charges"))}</span><span class="num">${iso(o.money(data.totals.amount - data.totals.charges))}</span></div>` : ""}`;

  const css = ticket
    ? `@page { size: 80mm auto; margin: 0; }
       body { width: 80mm; padding: 4mm 4mm 6mm; font-size: 12px; line-height: 1.35; }
       .c { text-align: center; } h1 { font-size: 15px; margin: 2px 0; } .logo { max-width: 38mm; max-height: 16mm; }
       hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; } .item { margin-bottom: 5px; } .n { font-weight: 600; }
       .grand { font-size: 15px; font-weight: 800; } .muted { color: #444; font-size: 10.5px; }`
    : `@page { size: A4 portrait; margin: 12mm; }
       body { font-size: 11.5px; line-height: 1.45; }
       .head { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #663399; padding-bottom: 8px; margin-bottom: 10px; }
       .head img { max-height: 16mm; max-width: 30mm; } h1 { margin: 0; font-size: 18px; color: #663399; } .muted { color: #666; font-size: 10px; }
       table { width: 100%; border-collapse: collapse; } th { background: #f3edfa; color: #3b2160; text-align: start; padding: 6px; font-size: 10px; text-transform: uppercase; }
       td { padding: 6px; border-bottom: 1px solid #ececf2; vertical-align: top; } th.num, td.num { text-align: end; } .mono { font-family: ui-monospace, Menlo, Consolas, monospace; }
       .bottom { display: flex; gap: 16px; margin-top: 12px; align-items: flex-start; } .bottom > div { flex: 1; }
       .box { border: 1px solid #e3e3ec; border-radius: 6px; padding: 8px 10px; } .box h4 { margin: 0 0 4px; font-size: 10px; text-transform: uppercase; color: #663399; }
       .grand { font-size: 14px; font-weight: 800; border-top: 2px solid #111; margin-top: 4px; padding-top: 4px; }`;

  const body = ticket
    ? `<div class="c">
         ${logo ? `<img class="logo" src="${esc(logo)}" alt="" onerror="this.remove()">` : ""}
         <h1>${esc((data.company && data.company.name) || "")}</h1>
         <div>${esc(title)}</div>
         <div>${iso(data.date)}</div>
       </div>
       <hr>${rows}<hr>${totals}${charges ? "<hr>" + charges : ""}`
    : `<div class="head">
         <div>${logo ? `<img src="${esc(logo)}" alt="" onerror="this.remove()">` : ""}<div><strong>${esc((data.company && data.company.name) || "")}</strong></div></div>
         <div style="text-align:end"><h1>${esc(title)}</h1><div>${iso(data.date)}</div></div>
       </div>
       <table>
         <thead><tr>
           <th>${esc(t("PosNext.time"))}</th><th>${esc(t("PosNext.item"))}</th><th class="num">${esc(t("PosNext.qty"))}</th>
           <th class="num">${esc(t("PosNext.unit_price"))}</th><th class="num">${esc(t("PosNext.total"))}</th>
         </tr></thead>
         <tbody>${rows}</tbody>
       </table>
       <div class="bottom">
         <div>${charges ? `<div class="box"><h4>${esc(t("PosNext.charges_sum"))}</h4>${charges}</div>` : ""}</div>
         <div class="box">${totals}</div>
       </div>`;

  return `<!doctype html><html dir="${o.dir}" lang="${esc(o.lang)}"><head><meta charset="utf-8"><title>${esc(title + " " + data.date)}</title>
<style>${BASE_CSS}${css}</style></head><body>${body}</body></html>`;
}

// Prints an HTML document from a hidden iframe (waits for the logo to load first)
export function printHtml(html) {
  const frame = document.createElement("iframe");
  frame.setAttribute("aria-hidden", "true");
  frame.style.cssText = "position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;";
  document.body.appendChild(frame);

  const doc = frame.contentWindow.document;
  doc.open();
  doc.write(html);
  doc.close();

  const images = Array.from(doc.images);
  const loaded = Promise.all(
    images.map(img => (img.complete ? Promise.resolve() : new Promise(resolve => { img.onload = img.onerror = resolve; })))
  );
  const timeout = new Promise(resolve => setTimeout(resolve, 2000));

  return Promise.race([loaded, timeout]).then(() => {
    const win = frame.contentWindow;
    const cleanup = () => setTimeout(() => frame.remove(), 500);
    win.addEventListener("afterprint", cleanup);
    setTimeout(() => frame.parentNode && frame.remove(), 120000);
    win.focus();
    win.print();
  });
}
