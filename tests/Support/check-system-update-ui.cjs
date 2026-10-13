const fs = require('fs'), vm = require('vm'), assert = require('assert');
const source = fs.readFileSync(process.argv[2] || 'public/cms/js/admin/system-update.js', 'utf8');
function fixture(saved = null, withForm = true) {
    const events = {}, timers = new Map(), intervals = new Map(), requests = [], navigation = [];
    let now = 1000000, seq = 0;
    const storage = new Map(saved ? [['wb-system-update:/updates', JSON.stringify(saved)]] : []);
    const status = {textContent:'Applying'}, elapsed = {}, history = {hidden:true}, button = {};
    const form = {getAttribute:()=>'/apply',querySelector:()=>button,addEventListener:(name,fn)=>events[name]=fn};
    const modal = {dataset:{wbHealthUrl:'/health',wbReturnUrl:'/updates',wbElapsedLabel:'Elapsed',wbWaitingLabel:'Waiting',wbReconnectingLabel:'Reconnecting',wbUnconfirmedLabel:'Unconfirmed',wbBaselineRun:'10'},addEventListener(){},classList:{add(){}}};
    const context = {document:{querySelector:s=>s.includes('form')?(withForm?form:null):modal,getElementById:id=>id==='wb-update-elapsed'?elapsed:id==='wb-update-history-link'?history:status},window:{fetch:true,location:{href:'https://example.test/updates',pathname:'/updates',assign:url=>navigation.push(url)}},URL,Date:{now:()=>now},FormData:class{},sessionStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v),removeItem:k=>storage.delete(k)},setTimeout:(fn,ms)=>{let id=++seq;timers.set(id,{fn,ms});return id},clearTimeout:id=>timers.delete(id),setInterval:fn=>{let id=++seq;intervals.set(id,fn);return id},clearInterval:id=>intervals.delete(id),fetch:(url,options)=>new Promise((resolve,reject)=>requests.push({url,options,resolve,reject}))};
    vm.runInNewContext(source,context);
    const flush = async()=>{for(let i=0;i<30;i++)await Promise.resolve()};
    return {requests,navigation,history,status,elapsed,button,events,storage,intervals,timers,flush,
        advance:ms=>{now+=ms;intervals.forEach(fn=>fn())},
        poll:()=>{const entry=[...timers].find(([,t])=>t.ms===2000);assert(entry,'poll scheduled');timers.delete(entry[0]);entry[1].fn()},
        reply:async(index,progress,code=200)=>{requests[index].resolve({ok:code===200,status:code,json:async()=>({progress})});await flush()}};
}
(async()=>{
    const f=fixture();
    await f.reply(0,{state:'success',run_id:10,terminal:true,running:false});
    assert.equal(f.navigation.length,0,'old success must not complete a new update');
    f.events.submit({preventDefault(){}});f.events.submit({preventDefault(){}});
    assert.equal(f.requests.filter(r=>r.options.method==='POST').length,1);
    f.requests[1].resolve({status:503});await f.flush();assert.equal(f.status.textContent,'Reconnecting');
    f.poll();await f.reply(2,{state:'idle',terminal:false,running:false});assert.equal(f.navigation.length,0);
    f.poll();await f.reply(3,{state:'running',operation_id:'op-1',run_id:11,running:true,terminal:false,stage_label:'Migrating',elapsed_ms:1200000});
    assert.equal(f.status.textContent,'Migrating');f.advance(1000);assert.equal(f.elapsed.textContent,'Elapsed: 20:01');
    f.poll();await f.reply(4,null,503);assert.equal(f.navigation.length,0);
    f.poll();await f.reply(5,{state:'interrupted',operation_id:'op-1',run_id:11,terminal:false});assert.equal(f.status.textContent,'Unconfirmed');assert.equal(f.history.hidden,false);
    f.poll();await f.reply(6,{state:'success',operation_id:'op-1',run_id:11,terminal:true,target_version:'2',installed_version:'1'});assert.equal(f.navigation.length,0,'must verify installed target');
    f.poll();await f.reply(7,{state:'success',operation_id:'op-1',run_id:11,terminal:true,target_version:'2',installed_version:'2'});
    assert.deepEqual(f.navigation,['/updates']);assert.equal(f.intervals.size,0);assert.equal(f.timers.size,0);assert.equal(f.storage.size,0);
    const resumed=fixture({operation:'op-1',baseline:10,started:0},false);
    assert(resumed.requests[0].url.includes('operation=op-1'));assert.equal(resumed.intervals.size,1);
    await resumed.reply(0,{state:'success',operation_id:'other',terminal:true});assert.equal(resumed.navigation.length,0);
    resumed.poll();await resumed.reply(1,{state:'restored',operation_id:'op-1',terminal:true});assert.deepEqual(resumed.navigation,['/updates']);
    const timeout=fixture({operation:'op-1',baseline:10,started:0});
    const t=[...timeout.timers].find(([,t])=>t.ms===15000);timeout.timers.delete(t[0]);t[1].fn();await timeout.flush();
    assert.equal(timeout.status.textContent,'Reconnecting');timeout.poll();assert.equal(timeout.requests.length,2);
    console.log('PASS: 503/reconnect, old history, duplicate submit, 20-minute duration, interruption, exact operation/target, restored outcome, resume and request timeout.');
})().catch(error=>{console.error(error);process.exitCode=1});
