const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('../frontend/node_modules/playwright');
const root = path.resolve(__dirname, '..');
const out = '/tmp/tracs-clients-validation';
fs.mkdirSync(out, { recursive: true });
const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date());
let clients = [];
let events = [];
const context = { schema_ready: true, allowed_actions: { manage: true, view_all: true }, user: { id: 1, name: 'Test Owner' }, users: [{ id: 1, name: 'Test Owner' }] };
function html(entryName) {
  const calendar = entryName === 'calendar';
  const prefix = calendar ? 'calendar-dist' : 'react-dist';
  const manifest = JSON.parse(fs.readFileSync(path.join(root, `public/assets/${prefix}/.vite/manifest.json`)));
  const key = calendar ? 'assets/react/calendar/main.jsx' : 'src/modules/clients/main.jsx';
  const css = new Set();
  function visit(k) { for (const file of manifest[k].css || []) css.add(file); for (const imp of manifest[k].imports || []) visit(imp); }
  visit(key);
  return `<!doctype html><html lang="en" data-theme="dark" data-visual-theme="tracs-v2"><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="test"><link rel="stylesheet" href="/assets/tracs.css"><link rel="stylesheet" href="/assets/css/themes/tracs-v2.css">${[...css].map(file=>`<link rel="stylesheet" href="/assets/${prefix}/${file}">`).join('')}<style>body{height:auto;min-height:100vh;overflow:auto}.fixture{padding:24px;max-width:1400px;margin:auto}@media(max-width:640px){.fixture{padding:12px}}</style></head><body><div class="fixture"><div id="${calendar ? 'calendar-react-root' : 'tracs-clients-root'}"></div></div><script type="module" src="/assets/${prefix}/${manifest[key].file}"></script></body></html>`;
}
const fixture = id => ({ id, company_name: id===1 ? 'PT Example' : 'PT Second', client_code: `CL-${id}`, owner_user_id: 1, owner_name: 'Test Owner', primary_contact_name: 'Test PIC', status: 'active', service_count: 1, addon_count: 0, service_types: 'VPS', nearest_renewal_date: today, next_action: 'Send invoice', next_action_due_at: today, contacts: [{ name:'Test PIC', email:'pic@example.test' }], services:[{id:1,service_name:'Production VPS',service_type:'VPS',status:'active',price:100000,billing_cycle:'monthly',renewal_date:today,addons:[]}], billing:[], followups:[], activity:[], renewal_history:[], notes:'Client operational notes', mrr_amount:100000, total_paid_amount:200000, outstanding_amount:100000 });
(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  try {
    const session = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
    const errors = [];
    const page = await session.newPage();
    page.on('pageerror', e => errors.push(e.message));
    await session.route('**/clients.php', route => route.fulfill({ contentType:'text/html', body:html('clients') }));
    await session.route('**/calendar.php', route => route.fulfill({ contentType:'text/html', body:html('calendar') }));
    await session.route('**/api/**', async route => {
      const req = route.request(); const url = new URL(req.url());
      const body = req.method()==='GET' ? {} : req.postDataJSON();
      let data = {};
      if (url.pathname.endsWith('/context.php')) data=context;
      else if (url.pathname.endsWith('/metadata.php')) data={users:[],divisions:[],roles:[],permissions:{can_create:false}};
      else if (url.pathname.endsWith('/events.php')) data={ events, sources:{clients:{available:true}} };
      else if (url.pathname.endsWith('/clients.php')) {
        if (req.method()==='POST') { const client={...fixture(clients.length+1),...body}; clients.push(client); data=client; }
        else { const q=url.searchParams.get('q') || ''; data={clients:clients.filter(c=>`${c.company_name} ${c.client_code} ${c.primary_contact_name}`.toLowerCase().includes(q.toLowerCase())),summary:{mrr_amount:100000,invoice_this_week:1},attention:[]}; }
      } else if (url.pathname.endsWith('/client.php')) data=clients.find(c=>String(c.id)===url.searchParams.get('id'));
      else if (url.pathname.endsWith('/actions.php')) {
        assert.equal(req.headers()['x-csrf-token'], 'test');
        if (body.action==='add_followup') {
          const client=clients.find(c=>c.id===body.client_id);
          const f={...body,id:1,reminder_id:10,status:'open',assignee_name:'Test Owner',due_at:body.due_at.replace('T',' ')};
          client.followups.push(f);
          events=[{id:'reminder_10',source:'clients',source_id:1,client_id:client.id,type:'reminder',title:`${client.company_name} · ${f.title}`,date:body.due_at.slice(0,10),start_time:'09:00',status:'upcoming',notes:'',meta:{client_id:client.id,client_name:client.company_name,reminder_title:f.title,activity_type:f.action_type,followup_id:1,reminder_id:10,editable:true,can_mark_done:true}}];
          data=client;
        } else if (body.action==='update_followup') {
          const f=clients[0].followups[0]; Object.assign(f,body);
          const event=events[0]; event.status=f.status==='completed'?'done':'upcoming'; event.date=f.due_at.slice(0,10); event.meta.reminder_title=f.title; event.title=`PT Example · ${f.title}`; event.meta.can_mark_done=event.status!=='done'; data=clients[0];
        }
      }
      await route.fulfill({json:{success:true,data}});
    });
    await page.goto('http://localhost:8080/clients.php');
    await page.getByText('No clients yet.', {exact:true}).waitFor();
    await page.addStyleTag({content:'*,*::before,*::after{animation:none!important;transition:none!important}'});
    assert.equal(await page.getByText('Needs Attention', {exact:true}).count(),0);
    await page.screenshot({path:`${out}/empty-desktop.png`,fullPage:true});
    await page.getByRole('button',{name:'Add Client',exact:true}).first().click();
    const modal=page.getByRole('dialog',{name:'Add Client',exact:true});
    await modal.getByLabel('Company',{exact:true}).fill('PT Example');
    await modal.getByLabel('PIC Name',{exact:true}).fill('Test PIC');
    await modal.getByRole('button',{name:'Save Client'}).click();
    await modal.waitFor({state:'hidden'});
    await page.locator('.client-details').waitFor();
    assert.equal(new URL(page.url()).pathname,'/clients.php');
    await page.getByRole('button',{name:'View Less'}).click();
    assert.equal(await page.locator('.client-details').count(),0);
    await page.locator('.clients-list').getByRole('button',{name:'View More'}).click();
    await page.getByRole('button',{name:'Add Record / Reminder'}).click();
    const record=page.getByRole('dialog',{name:'Add Record · PT Example',exact:true});
    await record.getByLabel('Title',{exact:true}).fill('Send quotation');
    await record.getByLabel('Action Type').selectOption('quotation');
    await record.getByLabel('Due At · Asia/Jakarta').fill(`${today}T09:00`);
    await record.getByRole('button',{name:'Add Followup'}).click();
    await record.waitFor({state:'hidden'});
    await page.locator('.clients-upcoming').getByText('Send quotation',{exact:true}).waitFor();
    await page.locator('.clients-calendar .panel-head').getByRole('button',{name:'View More'}).click();
    const full=page.getByRole('dialog',{name:'Client Calendar',exact:true});
    await full.getByRole('grid').waitFor();
    await full.getByRole('button',{name:'Next month'}).click();
    await full.getByRole('button',{name:'Previous month'}).click();
    await page.screenshot({path:`${out}/calendar-desktop.png`,fullPage:true});
    await full.getByRole('button', {name:/PT Example · Send quotation/}).click();
    await full.getByRole('button',{name:'Edit Reminder'}).click();
    await full.getByLabel('Title',{exact:true}).fill('Send updated quotation');
    await full.getByRole('button',{name:'Save Reminder'}).click();
    await full.getByRole('button', {name:/PT Example · Send updated quotation/}).waitFor();
    await full.getByRole('button',{name:'Close modal'}).click();
    await page.locator('.clients-upcoming').getByText('Send updated quotation',{exact:true}).waitFor();
    const mainCalendar=await session.newPage();
    mainCalendar.on('pageerror',e=>errors.push(e.message));
    await mainCalendar.goto('http://localhost:8080/calendar.php');
    await mainCalendar.getByRole('button',{name:'Agenda',exact:true}).click();
    await mainCalendar.getByText('PT Example · Send updated quotation',{exact:true}).click();
    await mainCalendar.getByRole('button',{name:'Mark Done'}).click();
    await page.bringToFront();
    await page.locator('.clients-upcoming').getByText('No client reminders in this period.').waitFor();
    await mainCalendar.close();
    await page.getByLabel('Search client, code, PIC').fill('not-a-client');
    await page.getByText('No clients match these filters.',{exact:true}).waitFor();
    await page.getByLabel('Search client, code, PIC').fill('');
    await page.locator('.clients-list > tbody > .client-row').waitFor();
    await page.getByRole('button',{name:'View Less'}).click();
    await page.screenshot({path:`${out}/populated-desktop.png`,fullPage:true});
    await page.setViewportSize({width:390,height:844});
    await page.locator('.clients-list').getByRole('button',{name:'View More'}).click();
    await page.locator('.client-details').waitFor();
    await page.screenshot({path:`${out}/expanded-mobile.png`,fullPage:true});
    const detailBox=await page.locator('.client-details').boundingBox();
    assert(detailBox.x>=0 && detailBox.x+detailBox.width<=390,'Expanded details are hidden by horizontal scrolling');
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),'Page overflows mobile viewport');
    await page.locator('.clients-calendar .panel-head').getByRole('button',{name:'View More'}).click();
    await page.screenshot({path:`${out}/calendar-mobile.png`,fullPage:true});
    const box=await full.boundingBox();
    assert(box.width<=390 && box.height<=844,'Calendar modal exceeds viewport');
    await full.getByRole('button',{name:'Close modal'}).focus();
    await page.keyboard.press('Escape');
    await full.waitFor({state:'hidden'});
    assert.deepEqual(errors,[]);
    console.log(`PASS: empty state, add modal, inline expansion, quotation creation, calendar popup/navigation/edit, main Calendar completion, cross-tab refresh, search, mobile overflow/modal sizing. Screenshots: ${out}`);
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
