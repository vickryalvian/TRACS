const assert = require('node:assert/strict');
const path = require('node:path');
const { build } = require('esbuild');
const { chromium } = require('../frontend/node_modules/playwright');
const root = path.resolve(__dirname, '..');
(async () => {
  const bundle = await build({ bundle: true, write: false, format: 'iife', jsx: 'automatic', alias: { react: path.join(root, 'node_modules/react'), 'react-dom': path.join(root, 'node_modules/react-dom') }, stdin: { resolveDir: root, loader: 'jsx', contents: `
    import React, {useState} from 'react';
    import {createRoot} from 'react-dom/client';
    import {ShiftCreateModal} from './frontend/src/modules/shift-assignment/components/ShiftCreateModal';
    import {ShiftEditModal} from './frontend/src/modules/shift-assignment/components/ShiftEditModal';
    import {BookScheduleModal} from './assets/react/calendar/components/BookScheduleModal';
    const assignment={id:7,agent:{id:1},assignment_date:'2026-10-01',type:'regular',status:'assigned',shift:{start_time:'09:00',end_time:'17:00'},break_minutes:0};
    const context={filters:{agents:[{id:1,name:'Agent'}],assignment_types:[{slug:'regular',name:'Regular'}]},shift_definitions:[],csrf:{token:'test'}};
    function App(){const [mode,setMode]=useState('');const close=()=>setMode('');return <><button onClick={()=>setMode('create')}>New assignment</button><button onClick={()=>setMode('edit')}>Edit assignment</button><button onClick={()=>setMode('calendar')}>New schedule</button><ShiftCreateModal open={mode==='create'} context={context} onClose={close} onCreated={async()=>{}} onToast={()=>{}}/><ShiftEditModal open={mode==='edit'} assignment={assignment} context={context} onClose={close} onUpdated={async()=>{}} onToast={()=>{}}/><BookScheduleModal open={mode==='calendar'} onClose={close} onSaved={async()=>{}} /></>}
    createRoot(document.getElementById('root')).render(<App/>);
  ` } });
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let fail = true;
    let requests = 0;
    await page.route('http://tracs.test/**', route => {
      const url = new URL(route.request().url());
      if(url.pathname.startsWith('/assets/'))return route.fulfill({path:path.join(root,'public',url.pathname)});
      if(url.pathname.startsWith('/api/')){ requests++; return route.fulfill({status:fail?422:200,json:fail?{success:false,message:'Test failure'}:{success:true,data:{assignment},message:'Saved'}}); }
      return route.fulfill({contentType:'text/html',body:'<!doctype html><html><head><link rel="stylesheet" href="/assets/tracs.css"></head><body><div id="root"></div><script src="/assets/unsaved-changes-guard.js"></script></body></html>'});
    });
    const assignment = { id: 7 };
    await page.goto('http://tracs.test/editors');
    await page.addScriptTag({content:bundle.outputFiles[0].text});
    const dirty=()=>page.evaluate(()=>TRACSUnsavedChanges.isDirty());
    const prompt=page.locator('.tracs-unsaved-dialog-overlay:not(.hidden)');
    for(const [button, field, changed, original] of [
      ['New assignment','[name="notes"]','Draft',''],
      ['Edit assignment','[name="start_time"]','10:00','09:00'],
      ['New schedule','[name="title"]','Draft','']
    ]){
      await page.getByRole('button',{name:button,exact:true}).click();
      const form=page.locator('form'); await form.waitFor(); assert.equal(await dirty(),false,button+' initialization');
      await page.keyboard.press('Escape'); await form.waitFor({state:'hidden'});
      await page.getByRole('button',{name:button,exact:true}).click();
      await page.locator(field).fill(changed); assert.equal(await dirty(),true);
      await form.getByRole('button',{name:'Cancel',exact:true}).click(); await prompt.waitFor();
      await prompt.getByRole('button',{name:'Keep Editing'}).click();
      await page.locator(field).fill(original); assert.equal(await dirty(),false,button+' reversion');
      await form.getByRole('button',{name:'Cancel',exact:true}).click(); await form.waitFor({state:'hidden'});
    }
    await page.getByRole('button',{name:'Edit assignment',exact:true}).click();
    await page.locator('[name="start_time"]').fill('10:00');
    const save=page.getByRole('button',{name:'Save Changes',exact:true});
    await save.click(); await save.waitFor({state:'visible'});
    await page.waitForFunction(()=>!document.querySelector('form').getAttribute('aria-busy') || document.querySelector('form').getAttribute('aria-busy')==='false');
    assert.equal(await dirty(),true); assert.equal(requests,1);
    fail=false; await save.click(); await page.locator('form').waitFor({state:'hidden'}); assert.equal(await dirty(),false); assert.equal(requests,2);
    assert.deepEqual(errors,[]);
    console.log('PASS: Shift create/edit and Calendar initialization, ESC, Cancel/Keep Editing, reversion; shift edit failed/successful save.');
  }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
