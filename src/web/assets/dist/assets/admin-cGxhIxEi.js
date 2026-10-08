var K=`datastar-fetch`,Se=`datastar-prop-change`,Rt=`datastar-ready`,tt=`datastar-scope-children`,ce=`datastar-signal-patch`,p=document,z=HTMLInputElement,R=MutationObserver,me=e=>e.replace(/([A-Z]+)([A-Z][a-z])/g,`$1-$2`).replace(/([a-z0-9])([A-Z])/g,`$1-$2`).replace(/([a-z])([0-9]+)/gi,`$1-$2`).replace(/([0-9]+)([a-z])/gi,`$1-$2`).replace(/[\s_]+/g,`-`).toLowerCase(),Mt=e=>me(e).replace(/-./g,e=>e[1].toUpperCase()),xt=e=>me(e).replace(/-/g,`_`),ke=e=>typeof e==`string`&&e.trim()===`true`,bn=/^(?:(?:async\s+)?function\b|(?:async\s*)?(?:\([^)]*\)|[A-Za-z_$][\w$]*)\s*=>)/,wt=e=>De([],`return (${e})`)(),ge=(e,t={})=>{let{reviveFunctionStrings:n=!1}=t;try{return n?JSON.parse(e,(e,t)=>{if(typeof t!=`string`)return t;let n=t.trim();if(!bn.test(n))return t;try{let e=wt(n);return typeof e==`function`?e:t}catch{return t}}):JSON.parse(e)}catch{return wt(e)}},Lt={camel:e=>e.replace(/-[a-z]/g,e=>e[1].toUpperCase()),snake:e=>e.replace(/-/g,`_`),pascal:e=>e[0].toUpperCase()+Lt.camel(e.slice(1))},L=(e,t,n=`camel`)=>{for(let r of t.get(`case`)||[n])e=Lt[r]?.(e)||e;return e},j=e=>`data-${e}`,nt=e=>e,vn=`https://data-star.dev/errors`,ee=(e,t,n={})=>{Object.assign(n,e);let r=xt(t),i=new URLSearchParams({metadata:JSON.stringify(n)}).toString(),a=JSON.stringify(n,null,2);return Error(`${t}
More info: ${vn}/${r}?${i}
Context: ${a}`)},Nt=j(`nonce`),Ot=p.documentElement,rt=Ot.getAttribute(Nt),st=rt!==null,Ae;if(st){if(!rt)throw ee({},`NonceRequired`);Ot.removeAttribute(Nt),Ae=window.trustedTypes?.createPolicy(`datastar`,{createHTML:e=>e,createScript:e=>e})}var Re=(e,t)=>{st&&(e.nonce=rt),e.text=Ae?Ae.createScript(t):t},it=e=>Ae?Ae.createHTML(e):e,Ct=new Map,De=(e,t)=>{if(!st)return Function(...e,t);let n=`function(${e.join(`,`)}){${t}
}`,r=Ct.get(n);if(r)return r;let i=p.createElement(`script`);Re(i,`document.currentScript.x=${n}`),p.head.appendChild(i),i.remove();let a=i.x;if(!a)throw Error(`Blocked by CSP.`);return Ct.set(n,a),a},C=Object.hasOwn??Object.prototype.hasOwnProperty.call,te=e=>typeof e==`object`&&!!e&&(Object.getPrototypeOf(e)===Object.prototype||Object.getPrototypeOf(e)===null),Pt=e=>{for(let t in e)if(C(e,t))return!1;return!0},le=(e,t)=>{for(let n in e){let r=e[n];te(r)||Array.isArray(r)?le(r,t):e[n]=t(r)}},Ie=e=>{let t={};for(let[n,r]of e){let e=n.split(`.`),i=e.pop(),a=e.reduce((e,t)=>e[t]??={},t);a[i]=r}return t},Ve=[],ot=[],Ue=0,$e=0,at=0,Me,Z,qe=0,N=()=>{Ue++},O=()=>{--Ue||(Ht(),Q())},P=e=>{Me={t:Z,u:Me},Z=e},F=()=>{Z=Me?.t,Me=Me?.u},we=e=>En.bind(0,{g:e,t:e,e:1}),ct=Symbol(`computed`),We=e=>{let t=Tn.bind(0,{e:17,h:e});return t[ct]=1,t},S=e=>{let t={T:e,e:2};Z&&ft(t,Z),P(t),N();try{t.T()}finally{O(),F()}return Vt.bind(0,t)},Ht=()=>{for(;$e<at;){let e=ot[$e];ot[$e++]=void 0,It(e,e.e&=-65)}$e=0,at=0},Ft=e=>`h`in e?kt(e):Dt(e,e.t),kt=e=>{P(e),$t(e);try{let t=e.t;return t!==(e.t=e.h(t))}finally{F(),qt(e)}},Dt=(e,t)=>(e.e=1,e.g!==(e.g=t)),lt=e=>{let t=e.e;if(!(t&64)){e.e=t|64;let n=e.r;n?lt(n.c):ot[at++]=e}},It=(e,t)=>{if(t&16||t&32&&Gt(e.s,e)){P(e),$t(e),N();try{e.T()}finally{O(),F(),qt(e)}return}t&32&&(e.e=t&-33);let n=e.s;for(;n;){let e=n.p,t=e.e;t&64&&It(e,e.e=t&-65),n=n.i}},En=(e,...t)=>{if(t.length){if(e.t!==(e.t=t[0])){e.e=17;let t=e.r;return t&&(Sn(t),Ue||Ht()),!0}return!1}let n=e.t;if(e.e&16&&Dt(e,n)){let t=e.r;t&&je(t)}return Z&&ft(e,Z),n},Tn=e=>{let t=e.e;if(t&16||t&32&&Gt(e.s,e)){if(kt(e)){let t=e.r;t&&je(t)}}else t&32&&(e.e=t&-33);return Z&&ft(e,Z),e.t},Vt=e=>{let t=e.s;for(;t;)t=Be(t,e);let n=e.r;n&&Be(n),e.e=0},ft=(e,t)=>{let n=t.l;if(n&&n.p===e)return;let r=n?n.i:t.s;if(r&&r.p===e){r.S=qe,t.l=r;return}let i=e.A;if(i&&i.S===qe&&i.c===t)return;let a=t.l=e.A={S:qe,p:e,c:t,d:n,i:r,y:i};r&&(r.d=a),n?n.i=a:t.s=a,i?i.n=a:e.r=a},Be=(e,t=e.c)=>{let n=e.p,r=e.d,i=e.i,a=e.n,o=e.y;if(i?i.d=r:t.l=r,r?r.i=i:t.s=i,a?a.y=o:n.A=o,o)o.n=a;else if(!(n.r=a)){if(`h`in n){let e=n.s;if(e){n.e=17;do e=Be(e,n);while(e)}}else`g`in n||Vt(n)}return i},Sn=e=>{let t=e.n,n;e:for(;;){let r=e.c,i=r.e;if(i&60?i&12?i&4?!(i&48)&&An(e,r)?(r.e=i|40,i&=1):i=0:r.e=i&-9|32:i=0:r.e=i|32,i&2&&lt(r),i&1){let i=r.r;if(i){let r=(e=i).n;r&&(n={t,u:n},t=r);continue}}if(e=t){t=e.n;continue}for(;n;)if(e=n.t,n=n.u,e){t=e.n;continue e}break}},$t=e=>{qe++,e.l=void 0,e.e=e.e&-57|4},qt=e=>{let t=e.l,n=t?t.i:e.s;for(;n;)n=Be(n,e);e.e&=-5},Gt=(e,t)=>{let n,r=0,i=!1;e:for(;;){let a=e.p,o=a.e;if(t.e&16)i=!0;else if((o&17)==17){if(Ft(a)){let e=a.r;e.n&&je(e),i=!0}}else if((o&33)==33){(e.n||e.y)&&(n={t:e,u:n}),e=a.s,t=a,++r;continue}if(!i){let t=e.i;if(t){e=t;continue}}for(;r--;){let r=t.r,a=r.n;if(a?(e=n.t,n=n.u):e=r,i){if(Ft(t)){a&&je(r),t=e.c;continue}i=!1}else t.e&=-33;if(t=e.c,e.i){e=e.i;continue e}}return i}},je=e=>{do{let t=e.c,n=t.e;(n&48)==32&&(t.e=n|16,n&2&&lt(t))}while(e=e.n)},An=(e,t)=>{let n=t.l;for(;n;){if(n===e)return!0;n=n.d}return!1},ue=e=>{let t=fe,n=e.split(`.`);for(let e of n){if(t==null||!C(t,e))return;t=t[e]}return t},Ge=(e,t=``)=>{let n=Array.isArray(e);if(n||te(e)){let r=n?[]:{};for(let n in e)r[n]=we(Ge(e[n],`${t+n}.`));let i=we(0);return new Proxy(r,{get(e,a){if(a!==`toJSON`||C(r,a))return n&&a in Array.prototype?(i(),r[a]):typeof a==`symbol`?r[a]:((!C(r,a)||r[a]()==null)&&(r[a]=we(``),Q(t+a,``),i(i()+1)),r[a]())},set(e,a,o){let s=t+a;if(n&&a===`length`){let e=r[a];if(r[a]=o,e>o){let n={};for(let t=o;t<e;t++)n[t]=null;Q(t.slice(0,-1),n),i(i()+1)}}else if(C(r,a)){if(o==null)delete r[a],Q(s,null),i(i()+1);else if(C(o,ct))r[a]=o,Q(s,``);else{let e=r[a](),t=`${s}.`;if(te(e)&&te(o)){for(let n in e)C(o,n)||(delete e[n],Q(t+n,null));for(let t in o){let n=o[t];e[t]!==n&&(e[t]=n)}}else r[a](Ge(o,t))&&Q(s,o)}}else o!=null&&(C(o,ct)?(r[a]=o,Q(s,``)):(r[a]=we(Ge(o,`${s}.`)),Q(s,o)),i(i()+1));return!0},deleteProperty(e,t){return delete r[t],i(i()+1),!0},ownKeys(){return i(),Reflect.ownKeys(r)},has(e,t){return i(),t in r}})}return e},Q=(e,t)=>{if(e!==void 0&&t!==void 0&&Ve.push([e,t]),!Ue&&Ve.length){let e=Ie(Ve);Ve.length=0,p.dispatchEvent(new CustomEvent(ce,{detail:e}))}},D=(e,{ifMissing:t}={})=>{N();for(let n in e)e[n]==null?t||delete fe[n]:Bt(e[n],n,fe,``,t);O()},A=(e,t)=>D(Ie(e),t),Bt=(e,t,n,r,i)=>{if(te(e)){C(n,t)&&(te(n[t])||Array.isArray(n[t]))||(n[t]={});for(let a in e)e[a]==null?i||delete n[t][a]:Bt(e[a],a,n[t],`${r+t}.`,i)}else i&&C(n,t)||(n[t]=e)},_t=e=>typeof e==`string`?RegExp(e.replace(/^\/|\/$/g,``)):e,U=({include:e=/.*/,exclude:t=/(?!)/}={},n=fe)=>{let r=_t(e),i=_t(t),a=[],o=(e,t=``)=>{for(let n in e){let s=t+n;te(e[n])?o(e[n],`${s}.`):r.test(s)&&!i.test(s)&&a.push([s,ue(s)])}};return o(n),Ie(a)},fe=Ge({}),ne=e=>e instanceof HTMLElement||e instanceof SVGElement||e instanceof MathMLElement,xe=new Map,ut=new Map,Jt=new Map,Kt=new Proxy({},{get:(e,t)=>xe.get(t)?.apply,has:(e,t)=>xe.has(t),ownKeys:()=>Reflect.ownKeys(xe),set:()=>!1,deleteProperty:()=>!1}),Ce=new Map,Je=[],pt=new Set,Le=new Set,jt=!1,m=e=>{Je.push(e),Je.length===1&&setTimeout(()=>{for(let e of Je)pt.add(e.name),ut.set(e.name,e);Je.length=0;let e=Le.size?[...Le]:[p.documentElement];for(let t of e)Nn(t,!Le.has(t));pt.clear()})},I=e=>{xe.set(e.name,e)};p.addEventListener(K,(e=>{let t=Jt.get(e.detail.type);t&&t.apply({error:ee.bind(0,{plugin:{type:`watcher`,name:t.name},element:{id:e.target.id,tag:e.target.tagName}})},e.detail.argsRaw)}));var Ne=e=>{Jt.set(e.name,e)},Ut=e=>{for(let t of e){let e=Ce.get(t);if(e&&Ce.delete(t))for(let t of e.values())for(let e of t.values())e()}},zt=j(`ignore`),Rn=`[${zt}]`,Qt=e=>e.hasAttribute(`${zt}__self`)||!!e.closest(Rn),Ke=(e,t)=>{for(let n of e)if(!Qt(n)){let e=new Set;for(let r in n.dataset){let i=r.replace(/[A-Z]/g,`-$&`).toLowerCase();e.add(i),dt(n,i,n.dataset[r],t)}for(let r of Array.from(n.attributes)){if(!r.name.startsWith(`data-`))continue;let i=r.name.slice(5);e.has(i)||dt(n,i,r.value,t)}}},wn=e=>{for(let{target:t,type:n,attributeName:r,addedNodes:i,removedNodes:a}of e)if(n===`childList`){for(let e of a)ne(e)&&(Ut([e]),Ut(e.querySelectorAll(`*`)));for(let e of i)ne(e)&&(Ke([e]),Ke(e.querySelectorAll(`*`)))}else if(n===`attributes`&&r.startsWith(`data-`)&&ne(t)&&!Qt(t)){let e=r.slice(5),n=nt(e);if(!n)continue;let i=t.getAttribute(r);if(i===null){let e=Ce.get(t);if(e){let t=e.get(n);if(t){for(let e of t.values())e();e.delete(n)}}}else dt(t,e,i)}},Mn=new R(wn),xn=e=>{let[t,...n]=e.split(`__`),[r,i]=t.split(/:(.+)/),a=new Map;for(let e of n){let[t,...n]=e.split(`.`);a.set(t,new Set(n))}return{pluginName:r,key:i,mods:a}},Ln=()=>Le.has(p.documentElement),Cn=()=>{jt||!Ln()||(jt=!0,p.dispatchEvent(new Event(Rt)))},Nn=(e=p.documentElement,t=!0)=>{ne(e)&&Ke([e],!0),Ke(e.querySelectorAll(`*`),!0),t&&(Mn.observe(e,{subtree:!0,childList:!0,attributes:!0}),Le.add(e),Cn())},dt=(e,t,n,r)=>{let i=nt(t);if(!i)return;let{pluginName:a,key:o,mods:s}=xn(i),c=ut.get(a);if((!r||pt.has(a))&&c){let t={el:e,rawKey:i,mods:s,error:ee.bind(0,{plugin:{type:`attribute`,name:c.name},element:{id:e.id,tag:e.tagName},expression:{rawKey:i,key:o,value:n}}),key:o,value:n,loadedPluginNames:{actions:new Set(xe.keys()),attributes:new Set(ut.keys())},rx:void 0},r=c.requirement&&(typeof c.requirement==`string`?c.requirement:c.requirement.key)||`allowed`,a=c.requirement&&(typeof c.requirement==`string`?c.requirement:c.requirement.value)||`allowed`,l=o!=null&&o!==``,u=n!=null&&n!==``;if(l){if(r===`denied`)throw t.error(`KeyNotAllowed`)}else if(r===`must`)throw t.error(`KeyRequired`);if(u){if(a===`denied`)throw t.error(`ValueNotAllowed`)}else if(a===`must`)throw t.error(`ValueRequired`);if(r===`exclusive`||a===`exclusive`){if(l&&u)throw t.error(`KeyAndValueProvided`);if(!l&&!u)throw t.error(`KeyOrValueRequired`)}let d=new Map;if(u){let r;t.rx=(...t)=>(r||=On(n,{R:c.returnsValue,w:c.argNames,M:d}),r(e,...t))}let f=c.apply(t);f&&d.set(`attribute`,f);let h=Ce.get(e);if(h){let e=h.get(i);if(e)for(let t of e.values())t()}else h=new Map,Ce.set(e,h);h.set(i,d)}},Wt=e=>e.split(`.`).reduce((e,t)=>`${e}['${t}']`,`$`),On=(e,{R:t=!1,w:n=[],M:r=new Map}={})=>{let i=``;if(t){let t=e.trim().match(/(?:\/(?:\\\/|[^/])*\/|"(?:\\"|[^"])*"|'(?:\\'|[^'])*'|`(?:\\`|[^`])*`|\(\s*(?:(?:function)\s*\(\s*\)|(?:\(\s*\))\s*=>)\s*(?:\{[\s\S]*?\}|[^;){]*)\s*\)\s*\(\s*\)|[^;])+/gm);if(t){let e=t.length-1,n=t[e].trim();n.startsWith(`return`)||(t[e]=`return (${n});`),i=t.join(`;
`)}}else i=e.trim();i=i.replace(/(?:"[^"\\]*(?:\\.[^"\\]*)*"|'[^'\\]*(?:\\.[^'\\]*)*'|`[^`\\$]*(?:(?:\\.|\$(?!\{))[^`\\$]*)*`)|\$\{([^{}]*)\}|\$(\w+(?:[.-]\w+)*)|@([A-Za-z_$][\w$]*)\(/g,(e,t,n,r)=>t===void 0?n?Wt(n):r?`__action("${r}",evt,`:e:`\${${t.replace(/\$(\w+(?:[.-]\w+)*)/g,(e,t)=>Wt(t)).replace(/@([A-Za-z_$][\w$]*)\(/g,`__action("$1",evt,`)}}`);try{let t=De([`el`,`$`,`__action`,`evt`,...n],i);return(n,...a)=>{let o=(t,a,...o)=>{let s=ee.bind(0,{plugin:{type:`action`,name:t},element:{id:n.id,tag:n.tagName},expression:{fnContent:i,value:e}}),c=Kt[t];if(c)return c({el:n,evt:a,error:s,cleanups:r},...o);throw s(`UndefinedAction`)};try{return t(n,fe,o,void 0,...a)}catch(t){throw console.error(t),ee({element:{id:n.id,tag:n.tagName},expression:{fnContent:i,value:e},error:t.message},`ExecuteExpression`)}}}catch(t){throw console.error(t),ee({expression:{fnContent:i,value:e},error:t.message},`GenerateExpression`)}};I({name:`peek`,apply(e,t){P();try{return t()}finally{F()}}}),I({name:`setAll`,apply(e,t,n){P();let r=U(n);le(r,()=>t),D(r),F()}}),I({name:`toggleAll`,apply(e,t){P();let n=U(t);le(n,e=>!e),D(n),F()}});var Zt=new Map,mt=e=>![`GET`,`DELETE`].includes(e),he=(e,t,n=!0)=>I({name:e,apply:async({el:r,evt:i,error:a,cleanups:o},s,{selector:c,headers:l,contentType:u=`json`,filterSignals:{include:d=/.*/,exclude:f=/(^|\.)_/}={},openWhenHidden:h=n,payload:g,requestCancellation:_=`auto`,retry:v=`auto`,retryInterval:y=1e3,retryScaler:b=2,retryMaxWait:x=3e4,retryMaxCount:w=10}={})=>{let T=_ instanceof AbortController?_:new AbortController,E=`@${e}`,k=_===`cleanup`,M;if(_===`auto`||k){let e=Zt.get(t)??new Map;e.get(s)?.abort(),e.set(s,T),Zt.set(t,e)}k&&(o.get(E)?.(),M=async()=>{T.abort(),await Promise.resolve()},o.set(E,M));let B=()=>{};try{if(!s?.length)throw a(`FetchNoUrlProvided`,{action:I});let e={Accept:`text/event-stream, text/html, application/json`,"Datastar-Request":!0};u===`json`&&mt(t)&&(e[`Content-Type`]=`application/json`),Object.assign(e,l);let n={b:``,method:t,headers:e,L:h,v,C:y,N:b,O:x,P:w,signal:T.signal,F:async e=>{e.status>=400&&pe(Pn,r,{status:e.status.toString()})},_:e=>{if(!e.E.startsWith(`datastar`))return;let t=e.E,n={};for(let t of e.m.split(`
`)){let e=t.indexOf(` `),r=t.slice(0,e),i=t.slice(e+1);(n[r]||=[]).push(i)}let i=Object.fromEntries(Object.entries(n).map(([e,t])=>[e,t.join(`
`)]));(!k||r.isConnected)&&pe(t,r,i)},onerror:e=>{if(Yt(e))throw a(`FetchExpectedTextEventStream`,{url:s})}},o=()=>{let o=new URL(s,p.baseURI),l=new URLSearchParams(o.search);if(u===`json`){P();let e=g===void 0?U({include:d,exclude:f}):g;F();let r=JSON.stringify(e);mt(t)?n.body=r:l.set(`datastar`,r)}else if(u===`form`){let o=c?p.querySelector(c):r.closest(`form`);if(!o)throw a(`FetchFormNotFound`,{action:I,selector:c});if(!o.noValidate&&!o.checkValidity()){o.reportValidity();return}let s=new FormData(o),u=r;if(r===o&&i instanceof SubmitEvent)u=i.submitter;else{let e=e=>e.preventDefault();o.addEventListener(`submit`,e),B=()=>{o.removeEventListener(`submit`,e)}}if(u instanceof HTMLButtonElement||u instanceof z&&u.type===`submit`){let e=u.getAttribute(`name`);e&&s.append(e,u.value)}let d=o.getAttribute(`enctype`)===`multipart/form-data`;d||(e[`Content-Type`]=`application/x-www-form-urlencoded`);let f=new URLSearchParams(s);if(mt(t))n.body=d?s:f;else for(let[e,t]of f)l.append(e,t)}else throw a(`FetchInvalidContentType`,{action:I,contentType:u});return o.search=l.toString(),n.b=o.toString(),n};pe(gt,r,{});try{await In(r,o,k)}catch(e){if(!Yt(e))throw a(`FetchFailed`,{method:t,url:s,error:e.message})}}finally{pe(ht,r,{}),B(),o.get(E)===M&&o.delete(E)}}});he(`get`,`GET`,!1),he(`patch`,`PATCH`),he(`post`,`POST`),he(`put`,`PUT`),he(`query`,`QUERY`),he(`delete`,`DELETE`);var gt=`started`,ht=`finished`,Pn=`error`,Fn=`retrying`,_n=`retries-failed`,pe=(e,t,n)=>p.dispatchEvent(new CustomEvent(K,{detail:{type:e,el:t,argsRaw:n}})),Yt=e=>`${e}`.includes(`text/event-stream`),Hn=async(e,t)=>{let n=e.getReader(),r=await n.read();for(;!r.done;)t(r.value),r=await n.read()},kn=e=>{let t,n,r,i=!1;return a=>{if(!t)t=a,n=0,r=-1;else{let e=new Uint8Array(t.length+a.length);e.set(t),e.set(a,t.length),t=e}let o=t.length,s=0;for(;n<o;){i&&=(t[n]===10&&(s=++n),!1);let a=-1;for(;n<o&&a===-1;++n)switch(t[n]){case 58:r===-1&&(r=n-s);break;case 13:i=!0;case 10:a=n}if(a===-1)break;e(t.subarray(s,a),r),s=n,r=-1}s===o?t=void 0:s&&(t=t.subarray(s),n-=s)}},Dn=(e,t,n)=>{let r=Xt(),i=new TextDecoder;return(a,o)=>{if(!a.length)n?.(r),r=Xt();else if(o>0){let n=i.decode(a.subarray(0,o)),s=o+(a[o+1]===32?2:1),c=i.decode(a.subarray(s));switch(n){case`data`:r.m=r.m?`${r.m}
${c}`:c;break;case`event`:r.E=c;break;case`id`:e(r.H=c);break;case`retry`:{let e=+c;Number.isNaN(e)||t(r.v=e);break}}}}},Xt=()=>({m:``,E:``,H:``,v:void 0}),In=(e,t,n)=>new Promise((r,i)=>{let a=t();if(!a)return;let{b:o,signal:s,headers:c,F:l,_:u,L:d,v:f,C:h,N:g,O:_,P:v,$:y,...b}=a,x={...c},w,T=()=>{let e=t();e&&(o=e.b,b.body=e.body,q())},E=()=>{w.abort(),p.hidden||T()};d||p.addEventListener(`visibilitychange`,E);let k,M=()=>{p.removeEventListener(`visibilitychange`,E),clearTimeout(k),w.abort()};s.addEventListener(`abort`,()=>{M(),r()});let B=l,H=0,W=h,G=()=>{H<v?(pe(Fn,e,{}),clearTimeout(k),k=setTimeout(T,h),H++,h=Math.min(h*g,_)):(pe(_n,e,{}),M(),i(`Max retries reached.`))},q=async()=>{w=new AbortController;let t=w.signal;try{let i=await fetch(o,{...b,headers:x,signal:t});await B(i);let a=async(t,i,a,o,...s)=>{let c={[a]:await i.text()};for(let e of s){let t=i.headers.get(`datastar-${me(e)}`);if(o){let n=o[e];n&&(t=typeof n==`string`?n:JSON.stringify(n))}t&&(c[e]=t)}(!n||e.isConnected)&&pe(t,e,c),M(),r()},s=i.status,c=s===204,l=s>=300&&s<400,d=s>=400&&s<600;if(s!==200){if(f!==`never`&&!c&&!l&&(f===`always`||f===`error`&&d)){G();return}M(),r();return}H=0,h=W;let g=i.headers.get(`Content-Type`);if(g?.includes(`text/html`))return await a(`datastar-patch-elements`,i,`elements`,y,`selector`,`mode`,`namespace`,`useViewTransition`);if(g?.includes(`application/json`))return await a(`datastar-patch-signals`,i,`signals`,y,`onlyIfMissing`);if(g?.includes(`text/javascript`)){let e=p.createElement(`script`),t=i.headers.get(`datastar-script-attributes`);if(t)for(let[n,r]of Object.entries(JSON.parse(t)))e.setAttribute(n,r);Re(e,await i.text()),p.head.appendChild(e),M();return}if(await Hn(i.body,kn(Dn(e=>{e?x[`last-event-id`]=e:delete x[`last-event-id`]},e=>{W=h=e},u))),f===`always`&&!l){G();return}M(),r()}catch{if(!t.aborted)try{G()}catch(e){M(),i(e)}}};q()});m({name:`attr`,requirement:{value:`must`},returnsValue:!0,apply({el:e,key:t,rx:n}){let r=(t,n)=>{n===``||n===!0?e.setAttribute(t,``):n===!1||n==null?e.removeAttribute(t):typeof n==`string`?e.setAttribute(t,n):typeof n==`function`?e.setAttribute(t,n.toString()):e.setAttribute(t,JSON.stringify(n,(e,t)=>typeof t==`function`?t.toString():t))},i=t?()=>{a.disconnect();let i=n();r(t,i),a.observe(e,{attributeFilter:[t]})}:()=>{a.disconnect();let t=n(),i=Object.keys(t);for(let e of i)r(e,t[e]);a.observe(e,{attributeFilter:i})},a=new R(i),o=S(i);return()=>{a.disconnect(),o()}}});var ze=(e,...t)=>({a:t=>t[e],f:(t,n)=>{t[e]=n},o:t}),en=(e,...t)=>({a:t=>t.getAttribute(e),f:(t,n)=>{t.setAttribute(e,`${n}`)},o:t}),tn=(e=!1,...t)=>({a:(t,n)=>n===`string`||e&&n===`undefined`?t.value:+t.value,f:(e,t)=>{e.value=`${t}`},o:t}),Vn=()=>{let e=new Set;return{a:(t,n)=>t.multiple?[...t.selectedOptions].map(t=>e.has(t.value)?+t.value:t.value):n===`string`||n===`undefined`?t.value:+t.value,f:(t,n)=>{if(!t.multiple){t.value=`${n}`;return}for(let r of t.options)n.includes(r.value)?(e.delete(r.value),r.selected=!0):n.includes(+r.value)?(e.add(r.value),r.selected=!0):r.selected=!1},o:[`change`]}},$n=/^data:(?<mime>[^;]+);base64,(?<contents>.*)$/,nn=Symbol(`empty`),rn=(e,t,n,r,i,a)=>{let o=j(CSS.escape(n)),s=t?`[${o}]`:`[${o}="${CSS.escape(r)}"]`;if(a===void 0&&e instanceof z&&e.type===`radio`){let e=[...p.querySelectorAll(s)].find(e=>e instanceof z&&e.checked);e&&A([[r,e.value]],{ifMissing:!0})}if(!Array.isArray(a)||e instanceof HTMLSelectElement&&e.multiple)return A([[r,i.a(e,typeof a)]],{ifMissing:!0}),r;let c=p.querySelectorAll(s),l=[],u=0;for(let t of c){if(l.push([`${r}.${u}`,i.a(t,typeof(C(a,u)?a[u]:void 0))]),e===t)break;u++}return A(l,{ifMissing:!0}),`${r}.${u}`};m({name:`bind`,requirement:`exclusive`,apply({el:e,key:t,rawKey:n,mods:r,value:i,error:a}){let o=t==null?i:L(t,r),s=r.get(`prop`),c=r.get(`event`),l=null;if(e instanceof z)switch(e.type){case`range`:case`number`:l=tn(!1,`input`);break;case`checkbox`:l={a:(e,t)=>e.value===`on`?t===`string`?e.checked?e.value:``:e.checked:t===`boolean`?e.checked:e.checked?e.value:``,f:(e,t)=>{e.checked=typeof t==`string`?t===e.value:t},o:[`input`]};break;case`radio`:e.getAttribute(`name`)?.length||e.setAttribute(`name`,o),l={a:(e,t)=>e.checked?t===`number`?+e.value:e.value:nn,f:(e,t)=>{e.checked=t===(typeof t==`number`?+e.value:e.value)},o:[`input`]};break;case`file`:{let t=()=>{let t=[...e.files||[]],n=[];Promise.all(t.map(e=>new Promise(t=>{let r=new FileReader;r.onload=()=>{if(typeof r.result!=`string`)throw a(`InvalidFileResultType`,{resultType:typeof r.result});let t=r.result.match($n);if(!t?.groups)throw a(`InvalidDataUri`,{result:r.result});n.push({name:e.name,contents:t.groups.contents,mime:t.groups.mime})},r.onloadend=()=>t(),r.readAsDataURL(e)}))).then(()=>{A([[o,n]])})};return e.addEventListener(`change`,t),()=>{e.removeEventListener(`change`,t)}}default:l=tn(!0,`input`)}else l=e instanceof HTMLSelectElement?Vn():e instanceof HTMLTextAreaElement?ze(`value`,`input`):e instanceof HTMLElement&&e.tagName.includes(`-`)?`value`in e?ze(`value`,`input`,`change`):en(`value`,`input`,`change`):e instanceof HTMLElement&&`value`in e?ze(`value`,`change`):en(`value`,`change`);if(!l)throw a(`InvalidBindAdapter`);let u=s&&[...s][0];if(s&&!u)throw a(`BindPropNameMissing`);u?l=ze(Mt(u),...c?[...c]:l.o):c&&(l.o=[...c]);let d=rn(e,t,n,o,l,ue(o)),f=()=>{let t=ue(d);if(t!=null){let n=l.a(e,typeof t);n!==nn&&A([[d,n]])}},h=()=>{l.f(e,ue(d))};for(let t of l.o)e.addEventListener(t,f);e.addEventListener(Se,f);let g=S(h),_=e instanceof HTMLSelectElement?new R(()=>{g(),d=rn(e,t,n,o,l,ue(o)),g=S(h)}):null;return _?.observe(e,{attributeFilter:[`multiple`]}),()=>{_?.disconnect(),g();for(let t of l.o)e.removeEventListener(t,f);e.removeEventListener(Se,f)}}}),m({name:`class`,requirement:{value:`must`},returnsValue:!0,apply({key:e,el:t,mods:n,rx:r}){e&&=L(e,n,`kebab`);let i,a=()=>{o.disconnect(),i=e?{[e]:r()}:r();for(let e in i){let n=e.split(/\s+/).filter(e=>e.length>0);if(i[e])for(let e of n)t.classList.contains(e)||t.classList.add(e);else for(let e of n)t.classList.contains(e)&&t.classList.remove(e)}o.observe(t,{attributeFilter:[`class`]})},o=new R(a),s=S(a);return()=>{o.disconnect(),s();for(let e in i){let n=e.split(/\s+/).filter(e=>e.length>0);for(let e of n)t.classList.remove(e)}}}}),m({name:`computed`,requirement:{value:`must`},returnsValue:!0,apply({key:e,mods:t,rx:n,error:r}){if(e)A([[L(e,t),We(n)]]);else{let e=Object.assign({},n());le(e,e=>{if(typeof e==`function`)return We(e);throw r(`ComputedExpectedFunction`)}),D(e)}}}),m({name:`effect`,requirement:{key:`denied`,value:`must`},apply:({rx:e})=>S(e)}),m({name:`indicator`,requirement:`exclusive`,apply({el:e,key:t,mods:n,value:r}){let i=t==null?r:L(t,n),a=0;A([[i,!1]]);let o=(t=>{let{type:n,el:r}=t.detail;if(r===e)switch(n){case gt:a++,A([[i,!0]]);break;case ht:a=Math.max(0,a-1),A([[i,a>0]])}});return p.addEventListener(K,o),()=>{a=0,A([[i,!1]]),p.removeEventListener(K,o)}}});var re=e=>{for(let t of e)return t.endsWith(`ms`)?+t.slice(0,-2):t.endsWith(`s`)?t.slice(0,-1)*1e3:Number.parseFloat(t);return 0},de=(e,t)=>e.has(t.toLowerCase()),sn=(e,t=``)=>{if(e)for(let t of e)return t;return t},yt=(e,t)=>(...n)=>{setTimeout(e,t,...n)},on=(e,t,n=!0,r=!1,i=!1)=>{let a=null,o=0;return(...s)=>{n&&!o?(e(...s),a=null):a=s,(!o||i)&&(o&&clearTimeout(o),o=setTimeout(()=>{r&&a!==null&&e(...a),a=null,o=0},t))}},ye=(e,t)=>{let n=t.get(`delay`);if(n){let t=re(n);e=yt(e,t)}let r=t.get(`debounce`);if(r){let t=re(r),n=de(r,`leading`),i=!de(r,`notrailing`);e=on(e,t,n,i,!0)}let i=t.get(`throttle`);if(i){let t=re(i),n=!de(i,`noleading`),r=de(i,`trailing`);e=on(e,t,n,r)}return e},bt=e=>`startViewTransition`in e,se=(e,t)=>{if(t.has(`viewtransition`)&&bt(p)){let t=e;e=(...e)=>p.startViewTransition(()=>t(...e))}return e};m({name:`init`,requirement:{key:`denied`,value:`must`},apply({rx:e,mods:t}){let n=()=>{N();try{e()}finally{O()}};n=se(n,t);let r=0,i=t.get(`delay`);i&&(r=re(i),r>0&&(n=yt(n,r))),n()}}),m({name:`json-signals`,requirement:{key:`denied`},apply({el:e,value:t,mods:n}){let r=n.has(`terse`)?0:2,i={};t&&(i=ge(t));let a=()=>{o.disconnect(),e.textContent=JSON.stringify(U(i),null,r),o.observe(e,{childList:!0,characterData:!0,subtree:!0})},o=new R(a),s=S(a);return()=>{o.disconnect(),s()}}}),m({name:`on`,requirement:`must`,argNames:[`evt`],apply({el:e,key:t,mods:n,rx:r}){let i=e;n.has(`window`)?i=window:n.has(`document`)&&(i=p);let a=e=>{N();try{r(e)}finally{O()}};a=se(a,n),a=ye(a,n);let o=L(t,n,`kebab`),s={capture:n.has(`capture`),passive:n.has(`passive`),once:n.has(`once`)};if(n.has(`outside`)){i=p;let t=a;a=n=>{e.contains(n?.target)||t(n)}}(o===K||o===ce)&&(i=p);let c=t=>{t&&(n.has(`prevent`)&&t.preventDefault(),n.has(`stop`)&&t.stopPropagation(),e instanceof HTMLFormElement&&o===`submit`&&t.preventDefault()),a(t)};return i.addEventListener(o,c,s),()=>{i.removeEventListener(o,c,s)}}});var an=(e,t,n)=>Math.max(t,Math.min(n,e)),vt=new WeakSet;m({name:`on-intersect`,requirement:{key:`denied`,value:`must`},apply({el:e,mods:t,rx:n}){let r=()=>{N();try{n()}finally{O()}};r=se(r,t),r=ye(r,t);let i={threshold:0};if(t.has(`full`))i.threshold=1;else if(t.has(`half`))i.threshold=.5;else{let e=t.get(`threshold`);e&&(i.threshold=an(Number(sn(e)),0,100)/100)}let a=t.has(`exit`),o=new IntersectionObserver(t=>{for(let n of t)n.isIntersecting!==a&&(r(),o&&vt.has(e)&&o.disconnect())},i);return o.observe(e),t.has(`once`)&&vt.add(e),()=>{t.has(`once`)||vt.delete(e),o&&=(o.disconnect(),null)}}}),m({name:`on-interval`,requirement:{key:`denied`,value:`must`},apply({mods:e,rx:t}){let n=()=>{N();try{t()}finally{O()}};n=se(n,e);let r=1e3,i=e.get(`duration`);i&&(r=re(i),de(i,`leading`)&&n());let a=setInterval(n,r);return()=>{clearInterval(a)}}}),m({name:`on-signal-patch`,requirement:{value:`must`},argNames:[`patch`],returnsValue:!0,apply({el:e,key:t,mods:n,rx:r,error:i}){if(t&&t!==`filter`)throw i(`KeyNotAllowed`);let a=j(`${this.name}-filter`),o=e.getAttribute(a),s={};o&&(s=ge(o));let c=!1,l=ye(e=>{if(c)return;P();let t=U(s,e.detail);if(F(),!Pt(t)){c=!0,N();try{r(t)}finally{O(),c=!1}}},n);return p.addEventListener(ce,l),()=>{p.removeEventListener(ce,l)}}}),m({name:`ref`,requirement:`exclusive`,apply({el:e,key:t,mods:n,value:r}){A([[t==null?r:L(t,n),e]])}});var cn=`none`,ln=`display`;m({name:`show`,requirement:{key:`denied`,value:`must`},returnsValue:!0,apply({el:e,rx:t}){let n=()=>{r.disconnect(),t()?e.style.display===cn&&e.style.removeProperty(ln):e.style.setProperty(ln,cn),r.observe(e,{attributeFilter:[`style`]})},r=new R(n),i=S(n);return()=>{r.disconnect(),i()}}}),m({name:`signals`,returnsValue:!0,apply({key:e,mods:t,rx:n}){let r=t.has(`ifmissing`);if(e){e=L(e,t);let i=n?.();A([[e,i]],{ifMissing:r})}else D(Object.assign({},n?.()),{ifMissing:r})}}),m({name:`style`,requirement:{value:`must`},returnsValue:!0,apply({key:e,el:t,rx:n}){let{style:r}=t,i=new Map,a=(e,t)=>{let n=i.get(e);!t&&t!==0?n!==void 0&&(n?r.setProperty(e,n):r.removeProperty(e)):(n===void 0&&i.set(e,r.getPropertyValue(e)),r.setProperty(e,String(t)))},o=()=>{if(s.disconnect(),e)a(e,n());else{let e=n();for(let[t,n]of i)t in e||(n?r.setProperty(t,n):r.removeProperty(t));for(let t in e)a(me(t),e[t])}s.observe(t,{attributeFilter:[`style`]})},s=new R(o),c=S(o);return()=>{s.disconnect(),c();for(let[e,t]of i)t?r.setProperty(e,t):r.removeProperty(e)}}}),m({name:`text`,requirement:{key:`denied`,value:`must`},returnsValue:!0,apply({el:e,rx:t}){let n=()=>{r.disconnect(),e.textContent=`${t()}`,r.observe(e,{childList:!0,characterData:!0,subtree:!0})},r=new R(n),i=S(n);return()=>{r.disconnect(),i()}}});var qn=[`remove`,`outer`,`inner`,`replace`,`prepend`,`append`,`before`,`after`],Gn=[`html`,`svg`,`mathml`];Ne({name:`datastar-patch-elements`,apply(e,t){let n=typeof t.selector==`string`?t.selector:``,r=typeof t.mode==`string`?t.mode:`outer`,i=typeof t.namespace==`string`?t.namespace:`html`,a=ke(t.useViewTransition),o=typeof t.viewTransitionSelector==`string`?t.viewTransitionSelector:``,s=t.elements;if(!qn.includes(r))throw e.error(`PatchElementsInvalidMode`,{mode:r});if(!n&&r!==`outer`&&r!==`replace`)throw e.error(`PatchElementsExpectedSelector`);if(!Gn.includes(i))throw e.error(`PatchElementsInvalidNamespace`,{namespace:i});let c={k:n,D:r,I:i,V:s};if(a){let t=p;if(o){let e=p.querySelector(o);e&&(t=e)}bt(t)?t.startViewTransition(()=>Et(e,c)):Et(e,c)}else Et(e,c)}});var Et=({error:e},{k:t,D:n,I:r,V:i})=>{let a=p.createDocumentFragment(),o=typeof i!=`string`&&!!i;if(typeof i==`string`){let e=i.replace(/<svg(\s[^>]*>|>)([\s\S]*?)<\/svg>/gim,``),t=/<\/html>/.test(e),n=/<\/head>/.test(e),o=/<\/body>/.test(e),s=r===`svg`?`svg`:r===`mathml`?`math`:``,c=s?`<${s}>${i}</${s}>`:i,l=t||n||o?i:`<body><template>${c}</template></body>`,u=new DOMParser().parseFromString(it(l),`text/html`);if(t)a.appendChild(u.documentElement);else if(n&&o)a.appendChild(u.head),a.appendChild(u.body);else if(n)a.appendChild(u.head);else if(o)a.appendChild(u.body);else if(s){let e=u.querySelector(`template`).content.querySelector(s);for(let t of e.childNodes)a.appendChild(t)}else a=u.querySelector(`template`).content}else i&&(i instanceof DocumentFragment?a=i:i instanceof Element&&a.appendChild(i));if(!t&&(n===`outer`||n===`replace`)){let t=Array.from(a.children);for(let r of t){let t;if(r instanceof HTMLHtmlElement)t=p.documentElement;else if(r instanceof HTMLBodyElement)t=p.body;else if(r instanceof HTMLHeadElement)t=p.head;else if(t=p.getElementById(r.id),!t){console.warn(e(`PatchElementsNoTargetsFound`),{element:{id:r.id}});continue}un(n,r,[t],!0)}}else{let r=p.querySelectorAll(t);if(!r.length){console.warn(e(`PatchElementsNoTargetsFound`),{selector:t});return}let i=o&&n!==`remove`?[r[0]]:r;i.length===1&&(o=!0),un(n,a,i,o)}},St=new WeakSet;for(let e of p.querySelectorAll(`script`))St.add(e);var gn=e=>{let t=e instanceof HTMLScriptElement?[e]:e.querySelectorAll(`script`);for(let e of t)if(!St.has(e)){let t=p.createElement(`script`);for(let{name:n,value:r}of e.attributes)t.setAttribute(n,r);Re(t,e.text),e.replaceWith(t),St.add(t)}},fn=(e,t,n,r)=>{let i=!1;for(let a of e){if(r&&i)break;let e=r?t:t.cloneNode(!0);gn(e),a[n](e),i=!0}},un=(e,t,n,r)=>{switch(e){case`remove`:for(let e of n)e.remove();break;case`outer`:case`inner`:{let i=!1;for(let a of n){if(r&&i)break;jn(a,r?t:t.cloneNode(!0),e),gn(a);let n=a.closest(`[data-scope-children]`);n&&n.dispatchEvent(new CustomEvent(tt,{bubbles:!1})),i=!0}}break;case`replace`:fn(n,t,`replaceWith`,r);break;case`prepend`:case`append`:case`before`:case`after`:fn(n,t,e,r)}},V=new Map,ve=new Set,be=new Map,Oe=new Set,Qe=p.createElement(`div`);Qe.hidden=!0;var Pe=j(`ignore-morph`),Bn=`[${Pe}]`,jn=(e,t,n=`outer`)=>{if(ne(e)&&ne(t)&&e.hasAttribute(Pe)&&t.hasAttribute(Pe)||e.parentElement?.closest(Bn))return;let r=p.createElement(`div`);r.append(t),p.body.insertAdjacentElement(`afterend`,Qe);let i=e.querySelectorAll(`[id]`);for(let{id:e,tagName:t}of i)be.has(e)?Oe.add(e):be.set(e,t);e instanceof Element&&e.id&&(be.has(e.id)?Oe.add(e.id):be.set(e.id,e.tagName)),ve.clear();let a=r.querySelectorAll(`[id]`);for(let{id:e,tagName:t}of a)ve.has(e)?Oe.add(e):be.get(e)===t&&ve.add(e);for(let e of Oe)ve.delete(e);be.clear(),Oe.clear(),V.clear();let o=n===`outer`?e.parentElement:e;mn(o,i),mn(r,a),hn(o,r,n===`outer`?e:null,e.nextSibling),Qe.remove()},hn=(e,t,n=null,r=null)=>{e instanceof HTMLTemplateElement&&t instanceof HTMLTemplateElement&&(e=e.content,t=t.content),n??=e.firstChild;for(let i of t.childNodes){if(n&&n!==r){let e=Un(i,n,r);if(e){if(e!==n){let t=n;for(;t&&t!==e;){let e=t;t=t.nextSibling,dn(e)}}Tt(e,i),n=e.nextSibling;continue}}if(i instanceof Element&&ve.has(i.id)){let t=p.getElementById(i.id),r=t;for(;r=r.parentNode;){let e=V.get(r);e&&(e.delete(i.id),e.size||V.delete(r))}yn(e,t,n),Tt(t,i),n=t.nextSibling;continue}if(V.has(i)){let t=i.namespaceURI,r=i.tagName,a=t&&t!==`http://www.w3.org/1999/xhtml`?p.createElementNS(t,r):p.createElement(r);e.insertBefore(a,n),Tt(a,i),n=a.nextSibling}else{let t=p.importNode(i,!0);e.insertBefore(t,n),n=t.nextSibling}}for(;n&&n!==r;){let e=n;n=n.nextSibling,dn(e)}},Un=(e,t,n)=>{let r=null,i=e.nextSibling,a=0,o=0,s=V.get(e)?.size||0,c=t;for(;c&&c!==n;){if(pn(c,e)){let t=!1,n=V.get(c),i=V.get(e);if(i&&n){for(let e of n)if(i.has(e)){t=!0;break}}if(t)return c;if(!r&&!V.has(c)){if(!s)return c;r=c}}if(o+=V.get(c)?.size||0,o>s)break;r===null&&i&&pn(c,i)&&(a++,i=i.nextSibling,a>=2&&(r=void 0)),c=c.nextSibling}return r||null},pn=(e,t)=>e.nodeType===t.nodeType&&e.tagName===t.tagName&&(!e.id||e.id===t.id),dn=e=>{V.has(e)?yn(Qe,e,null):e.parentNode?.removeChild(e)},yn=(e,t,n)=>{if(`moveBefore`in e){e.moveBefore(t,n);return}e.insertBefore(t,n)},Wn=j(`preserve-attr`),Tt=(e,t)=>{let n=t.nodeType;if(n===1){let n=e,r=t,i=n.hasAttribute(`data-scope-children`);if(n.hasAttribute(Pe)&&r.hasAttribute(Pe))return e;let a=(t.getAttribute(Wn)??``).split(` `),o=(e,t,n)=>{let r=t.hasAttribute(n);return e.hasAttribute(n)!==r&&!a.includes(n)&&(e[n]=r,!0)},s=!1;if(n instanceof z&&r instanceof z&&r.type!==`file`){let e=r.getAttribute(`value`);n.getAttribute(`value`)!==e&&!a.includes(`value`)&&(n.value=e??``,s=!0),s=o(n,r,`checked`)||s,o(n,r,`disabled`)}else if(n instanceof HTMLTextAreaElement&&r instanceof HTMLTextAreaElement){let e=r.value;n.defaultValue!==e&&(n.value=e,s=!0)}else n instanceof HTMLOptionElement&&r instanceof HTMLOptionElement&&(s=o(n,r,`selected`)||s);for(let{name:e,value:t}of r.attributes)n.getAttribute(e)!==t&&!a.includes(e)&&n.setAttribute(e,t);for(let{name:e}of Array.from(n.attributes))!r.hasAttribute(e)&&!a.includes(e)&&n.removeAttribute(e);s&&(n instanceof HTMLOptionElement?n.closest(`select`):n)?.dispatchEvent(new Event(Se,{bubbles:!0})),i&&!n.hasAttribute(`data-scope-children`)&&n.setAttribute(`data-scope-children`,``),n instanceof HTMLTemplateElement&&r instanceof HTMLTemplateElement?n.innerHTML=it(r.innerHTML):n.isEqualNode(r)||hn(n,r),i&&n.dispatchEvent(new CustomEvent(tt,{bubbles:!1}))}return(n===8||n===3)&&e.nodeValue!==t.nodeValue&&(e.nodeValue=t.nodeValue),e},mn=(e,t)=>{for(let n of t)if(ve.has(n.id)){let t=n;for(;t&&t!==e;){let e=V.get(t);e||(e=new Set,V.set(t,e)),e.add(n.id),t=t.parentElement}}};Ne({name:`datastar-patch-signals`,apply({error:e},{signals:t,onlyIfMissing:n}){if(typeof t!=`string`)throw e(`PatchSignalsExpectedSignals`);let r=ke(n);D(ge(t),{ifMissing:r})}}),(function(window){const DatastarInspector={config:{position:`right`,width:`400px`,height:`100vh`,theme:`dark`,startMinimized:!1,hotkey:`Alt+Shift+D`},signals:new Map,changeLog:[],rootSignals:null,isInitialized:!1,isVisible:!0,container:null,expandedSignals:new Set,init(e={}){if(this.isInitialized&&this.container&&document.body.contains(this.container))return;this.isInitialized&&(!this.container||!document.body.contains(this.container))&&(this.isInitialized=!1,this.container=null);let t=localStorage.getItem(`datastar-inspector-position`);if(t&&[`right`,`left`,`bottom`].includes(t)&&(e.position=t),Object.assign(this.config,e),typeof Datastar>`u`||!Datastar.root){console.log(`📊 Datastar Inspector: Waiting for Datastar.root to be available...`);let e=setInterval(()=>{window.Datastar&&window.Datastar.root&&(clearInterval(e),this.initializeInspector())},100);setTimeout(()=>{clearInterval(e),this.isInitialized||console.error(`📊 Datastar Inspector: Failed to find Datastar.root after 5 seconds. Make sure Datastar is loaded and window.Datastar.root is exposed.`)},5e3)}else this.initializeInspector()},initializeInspector(){window.Datastar&&window.Datastar.root?(this.rootSignals=window.Datastar.root,console.log(`📊 Datastar Inspector: Signals exposed via Datastar.root`)):console.warn(`📊 Datastar Inspector: Datastar.root not available, signal scanning may be limited`),document.addEventListener(`datastar-signal-patch`,e=>{this.handleSignalPatch(e.detail)}),this.createUI(),this.setupHotkey(),setTimeout(()=>this.scanSignals(),100),setInterval(()=>this.scanSignals(),2e3),this.isInitialized=!0,console.log(`📊 Datastar Inspector initialized. Press `+this.config.hotkey+` to toggle.`)},setupHotkey(){document.addEventListener(`keydown`,e=>{let t=this.config.hotkey.split(`+`).map(e=>e.trim().toLowerCase()),n=[];e.altKey&&n.push(`alt`),e.ctrlKey&&n.push(`ctrl`),e.shiftKey&&n.push(`shift`),e.metaKey&&n.push(`meta`),n.push(e.key.toLowerCase()),t.every(e=>n.includes(e))&&(e.preventDefault(),this.toggle())})},createUI(){let e=document.createElement(`style`);e.textContent=this.getStyles(),document.head.appendChild(e),this.container=document.createElement(`div`),this.container.id=`datastar-inspector`,this.container.className=`dsi-container dsi-${this.config.position} dsi-${this.config.theme}`,this.config.startMinimized?(this.container.classList.add(`dsi-minimized`),this.isVisible=!1):this.adjustPageLayout(this.config.position,!0),this.container.innerHTML=`
                <div class="dsi-header">
                    <div class="dsi-title">
                        <span class="dsi-icon">📊</span>
                        <span>Datastar Inspector</span>
                        <span class="dsi-count" id="dsi-signal-count">0 signals</span>
                    </div>
                    <div class="dsi-controls">
                        <select class="dsi-position-selector" onchange="DatastarInspector.changePosition(this.value)" title="Change Position">
                            <option value="right" ${this.config.position===`right`?`selected`:``}>→</option>
                            <option value="left" ${this.config.position===`left`?`selected`:``}>←</option>
                            <option value="bottom" ${this.config.position===`bottom`?`selected`:``}>↓</option>
                        </select>
                        <button class="dsi-btn" onclick="DatastarInspector.clearLog()" title="Clear Log">🗑️</button>
                        <button class="dsi-btn" onclick="DatastarInspector.exportSignals()" title="Export Signals">💾</button>
                        <button class="dsi-btn" onclick="DatastarInspector.minimize()" title="Minimize">_</button>
                        <button class="dsi-btn" onclick="DatastarInspector.close()" title="Close">✕</button>
                    </div>
                </div>
                <div class="dsi-body">
                    <div class="dsi-tabs">
                        <button class="dsi-tab dsi-tab-active" onclick="DatastarInspector.showTab('signals')">Signals</button>
                        <button class="dsi-tab" onclick="DatastarInspector.showTab('changes')">Changes</button>
                        <button class="dsi-tab" onclick="DatastarInspector.showTab('console')">Console</button>
                    </div>
                    <div class="dsi-content">
                        <div class="dsi-panel dsi-panel-signals dsi-panel-active" id="dsi-signals">
                            <div class="dsi-search">
                                <input type="text" placeholder="Search signals..." oninput="DatastarInspector.filterSignals(this.value)">
                            </div>
                            <div class="dsi-signal-list" id="dsi-signal-list"></div>
                        </div>
                        <div class="dsi-panel dsi-panel-changes" id="dsi-changes">
                            <div class="dsi-change-log" id="dsi-change-log"></div>
                        </div>
                        <div class="dsi-panel dsi-panel-console" id="dsi-console">
                            <div class="dsi-console-input">
                                <input type="text" placeholder="Enter expression (e.g., $signals.count())" onkeypress="if(event.key==='Enter') DatastarInspector.executeCommand(this)">
                            </div>
                            <div class="dsi-console-output" id="dsi-console-output"></div>
                        </div>
                    </div>
                </div>
                <div class="dsi-status">
                    <span id="dsi-status-text">Ready</span>
                    <span id="dsi-last-update">Never updated</span>
                </div>
            `,document.body.appendChild(this.container)},getStyles(){return`
                body.dsi-push-right {
                    margin-right: 400px !important;
                    transition: margin-right 0.3s ease;
                }
                
                body.dsi-push-left {
                    margin-left: 400px !important;
                    transition: margin-left 0.3s ease;
                }
                
                body.dsi-push-bottom {
                    margin-bottom: 400px !important;
                    transition: margin-bottom 0.3s ease;
                }
                
                .dsi-container {
                    position: fixed;
                    z-index: 999999;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, monospace;
                    font-size: 12px;
                    box-shadow: 0 0 20px rgba(0,0,0,0.5);
                    display: flex;
                    flex-direction: column;
                    transition: transform 0.3s ease;
                    max-height: 100vh;
                    overflow: hidden;
                }
                
                .dsi-container.dsi-right {
                    right: 0;
                    top: 0;
                    width: 400px;
                    height: 100vh;
                    border-left: 1px solid #3e3e3e;
                }
                
                .dsi-container.dsi-left {
                    left: 0;
                    top: 0;
                    width: 400px;
                    height: 100vh;
                    border-right: 1px solid #3e3e3e;
                }
                
                .dsi-container.dsi-bottom {
                    bottom: 0;
                    left: 0;
                    right: 0;
                    height: 400px;
                    max-height: 50vh;
                    border-top: 1px solid #3e3e3e;
                }
                
                .dsi-container.dsi-minimized {
                    transform: translateX(100%);
                }
                
                .dsi-container.dsi-left.dsi-minimized {
                    transform: translateX(-100%);
                }
                
                .dsi-container.dsi-bottom.dsi-minimized {
                    transform: translateY(100%);
                }
                
                /* Dark theme */
                .dsi-dark {
                    background: #1e1e1e;
                    color: #d4d4d4;
                }
                
                .dsi-dark .dsi-header {
                    background: #2d2d30;
                    border-bottom: 1px solid #3e3e3e;
                }
                
                .dsi-dark .dsi-signal-item {
                    background: #252526;
                    border: 1px solid #3e3e3e;
                }
                
                .dsi-dark .dsi-signal-item:hover {
                    background: #2d2d30;
                }
                
                .dsi-dark input {
                    background: #3c3c3c;
                    border: 1px solid #3e3e3e;
                    color: #d4d4d4;
                }
                
                /* Light theme */
                .dsi-light {
                    background: #ffffff;
                    color: #333333;
                }
                
                .dsi-light .dsi-header {
                    background: #f3f3f3;
                    border-bottom: 1px solid #e0e0e0;
                }
                
                .dsi-light .dsi-signal-item {
                    background: #f9f9f9;
                    border: 1px solid #e0e0e0;
                }
                
                .dsi-light .dsi-signal-item:hover {
                    background: #f0f0f0;
                }
                
                .dsi-light input {
                    background: #ffffff;
                    border: 1px solid #e0e0e0;
                    color: #333333;
                }
                
                /* Common styles */
                .dsi-header {
                    padding: 10px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    user-select: none;
                }
                
                .dsi-title {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    font-weight: 500;
                }
                
                .dsi-icon {
                    font-size: 16px;
                }
                
                .dsi-count {
                    background: #007acc;
                    color: white;
                    padding: 2px 6px;
                    border-radius: 10px;
                    font-size: 10px;
                }
                
                .dsi-controls {
                    display: flex;
                    gap: 5px;
                }
                
                .dsi-btn {
                    background: transparent;
                    border: none;
                    color: inherit;
                    cursor: pointer;
                    padding: 4px 8px;
                    border-radius: 3px;
                    font-size: 14px;
                }
                
                .dsi-btn:hover {
                    background: rgba(127, 127, 127, 0.2);
                }
                
                .dsi-position-selector {
                    background: transparent;
                    border: 1px solid rgba(127, 127, 127, 0.3);
                    color: inherit;
                    cursor: pointer;
                    padding: 4px;
                    border-radius: 3px;
                    font-size: 14px;
                    margin-right: 5px;
                }
                
                .dsi-position-selector:hover {
                    background: rgba(127, 127, 127, 0.2);
                }
                
                .dsi-body {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                }
                
                .dsi-tabs {
                    display: flex;
                    border-bottom: 1px solid #3e3e3e;
                }
                
                .dsi-tab {
                    flex: 1;
                    padding: 8px;
                    background: transparent;
                    border: none;
                    color: inherit;
                    cursor: pointer;
                    border-bottom: 2px solid transparent;
                }
                
                .dsi-tab:hover {
                    background: rgba(127, 127, 127, 0.1);
                }
                
                .dsi-tab-active {
                    border-bottom-color: #007acc;
                }
                
                .dsi-content {
                    flex: 1;
                    overflow: hidden;
                    position: relative;
                }
                
                .dsi-panel {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    display: none;
                    flex-direction: column;
                }
                
                .dsi-panel-active {
                    display: flex;
                }
                
                .dsi-search {
                    padding: 10px;
                }
                
                .dsi-search input {
                    width: 100%;
                    padding: 6px;
                    border-radius: 3px;
                }
                
                .dsi-signal-list {
                    flex: 1;
                    overflow-y: auto;
                    padding: 10px;
                }
                
                .dsi-signal-item {
                    margin-bottom: 8px;
                    border-radius: 4px;
                    transition: all 0.2s;
                }
                
                .dsi-signal-item.changed {
                    animation: highlight 0.5s;
                }
                
                @keyframes highlight {
                    0% { background: #4ec9b0 !important; }
                    100% { background: inherit; }
                }
                
                .dsi-signal-header {
                    padding: 8px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    cursor: pointer;
                }
                
                .dsi-signal-path {
                    font-family: 'Cascadia Code', 'Courier New', monospace;
                    color: #9cdcfe;
                    font-weight: 500;
                }
                
                .dsi-signal-value {
                    font-family: 'Cascadia Code', 'Courier New', monospace;
                    color: #ce9178;
                }
                
                .dsi-signal-value.null { color: #569cd6; }
                .dsi-signal-value.boolean { color: #569cd6; }
                .dsi-signal-value.number { color: #b5cea8; }
                .dsi-signal-value.object { color: #4ec9b0; }
                
                .dsi-signal-details {
                    padding: 0 8px 8px;
                    display: none;
                    border-top: 1px solid #3e3e3e;
                    font-size: 11px;
                }
                
                .dsi-signal-item.expanded .dsi-signal-details {
                    display: block;
                }
                
                .dsi-change-log {
                    flex: 1;
                    overflow-y: auto;
                    padding: 10px;
                }
                
                .dsi-change-entry {
                    margin-bottom: 5px;
                    padding: 5px;
                    background: rgba(127, 127, 127, 0.1);
                    border-radius: 3px;
                }
                
                .dsi-timestamp {
                    color: #858585;
                    margin-right: 10px;
                }
                
                .dsi-console-input {
                    padding: 10px;
                    border-bottom: 1px solid #3e3e3e;
                }
                
                .dsi-console-input input {
                    width: 100%;
                    padding: 6px;
                    border-radius: 3px;
                    font-family: 'Cascadia Code', 'Courier New', monospace;
                }
                
                .dsi-console-output {
                    flex: 1;
                    overflow-y: auto;
                    padding: 10px;
                    font-family: 'Cascadia Code', 'Courier New', monospace;
                }
                
                .dsi-console-line {
                    margin-bottom: 5px;
                    padding: 3px;
                }
                
                .dsi-console-result { color: #4ec9b0; }
                .dsi-console-error { color: #f48771; }
                
                .dsi-status {
                    padding: 8px 10px;
                    background: #007acc;
                    color: white;
                    font-size: 11px;
                    display: flex;
                    justify-content: space-between;
                }
            `},handleSignalPatch(e){let t=new Date().toLocaleTimeString(),n=new Set;document.querySelectorAll(`.dsi-signal-item.expanded`).forEach(e=>{let t=e.getAttribute(`data-signal-path`);t&&n.add(t)});for(let[n,r]of Object.entries(e)){let e=this.signals.get(n);this.signals.set(n,r),this.changeLog.unshift({timestamp:t,path:n,oldValue:e,newValue:r}),this.changeLog.length>100&&this.changeLog.pop()}n.forEach(e=>this.expandedSignals.add(e)),this.updateUI();for(let[t,n]of Object.entries(e)){let e=document.querySelector(`[data-signal-path="${t}"]`);e&&(e.classList.remove(`changed`),e.offsetWidth,e.classList.add(`changed`))}},scanSignals(){if(!this.rootSignals)return;let e=new Map(this.signals);this.signals.clear(),this.traverseObject(this.rootSignals,``);let t=e.size!==this.signals.size;if(!t)for(let[n,r]of this.signals){let i=e.get(n);if(JSON.stringify(i)!==JSON.stringify(r)){t=!0;break}}t&&this.updateUI()},traverseObject(e,t){for(let n in e){let r=t?`${t}.${n}`:n;try{let t=typeof e[n]==`function`?e[n]():e[n];t!==void 0&&(this.signals.set(r,t),typeof t==`object`&&t&&!Array.isArray(t)&&this.traverseObject(t,r))}catch{this.signals.set(r,`<computed>`)}}},getValueType(e){return e==null?`null`:typeof e==`boolean`?`boolean`:typeof e==`number`?`number`:typeof e==`object`?`object`:`string`},formatValue(e){if(e===null)return`null`;if(e===void 0)return`undefined`;if(typeof e==`object`)try{return JSON.stringify(e,null,2)}catch{return`[Object]`}return String(e)},updateUI(){if(!this.container||!document.body.contains(this.container))return;let e=document.getElementById(`dsi-signal-count`);e&&(e.textContent=`${this.signals.size} signals`);let t=document.getElementById(`dsi-last-update`);t&&(t.textContent=new Date().toLocaleTimeString()),this.updateSignalList(),this.updateChangeLog()},updateSignalList(){let e=document.getElementById(`dsi-signal-list`);if(!e||!document.body.contains(e))return;let t=e.parentElement.querySelector(`input`)?.value.toLowerCase()||``;document.querySelectorAll(`.dsi-signal-item.expanded`).forEach(e=>{let t=e.getAttribute(`data-signal-path`);t&&this.expandedSignals.add(t)});let n=``;Array.from(this.signals.entries()).sort((e,t)=>e[0].localeCompare(t[0])).forEach(([e,r])=>{if(t&&!e.toLowerCase().includes(t))return;let i=this.getValueType(r),a=this.formatValue(r),o=a.length>50?a.substring(0,50)+`...`:a,s=this.expandedSignals.has(e);n+=`
                    <div class="dsi-signal-item ${s?`expanded`:``}" data-signal-path="${e}">
                        <div class="dsi-signal-header" onclick="DatastarInspector.toggleSignalByElement(this)">
                            <span class="dsi-signal-path">${e}</span>
                            <span class="dsi-signal-value ${i}">${o}</span>
                        </div>
                        <div class="dsi-signal-details">
                            <div>Type: ${i}</div>
                            <div>Value: <pre>${a}</pre></div>
                            <button onclick="DatastarInspector.copyPathByElement(this)">Copy Path</button>
                            <button onclick="DatastarInspector.copyValueByElement(this)">Copy Value</button>
                        </div>
                    </div>
                `}),e.innerHTML=n||`<div style="padding: 20px; text-align: center; opacity: 0.5;">No signals found</div>`},updateChangeLog(){let e=document.getElementById(`dsi-change-log`);if(!e||!document.body.contains(e))return;let t=``;this.changeLog.slice(0,50).forEach(e=>{t+=`
                    <div class="dsi-change-entry">
                        <span class="dsi-timestamp">${e.timestamp}</span>
                        <span class="dsi-signal-path">${e.path}</span>: 
                        ${this.formatValue(e.oldValue)} → ${this.formatValue(e.newValue)}
                    </div>
                `}),e.innerHTML=t||`<div style="padding: 20px; text-align: center; opacity: 0.5;">No changes yet</div>`},toggleSignal(e,t){let n=e.parentElement;n.classList.contains(`expanded`)?(n.classList.remove(`expanded`),this.expandedSignals.delete(t)):(n.classList.add(`expanded`),this.expandedSignals.add(t))},toggleSignalByElement(e){let t=e.parentElement,n=t.getAttribute(`data-signal-path`);t.classList.contains(`expanded`)?(t.classList.remove(`expanded`),this.expandedSignals.delete(n)):(t.classList.add(`expanded`),this.expandedSignals.add(n))},copyPathByElement(e){let t=e.closest(`.dsi-signal-item`).getAttribute(`data-signal-path`);this.copyPath(t)},copyValueByElement(e){let t=e.closest(`.dsi-signal-item`).getAttribute(`data-signal-path`);this.copyValue(t)},showTab(e){document.querySelectorAll(`.dsi-tab`).forEach(e=>{e.classList.remove(`dsi-tab-active`)}),event.target.classList.add(`dsi-tab-active`),document.querySelectorAll(`.dsi-panel`).forEach(e=>{e.classList.remove(`dsi-panel-active`)}),document.getElementById(`dsi-${e}`).classList.add(`dsi-panel-active`)},filterSignals(e){this.updateSignalList()},executeCommand(input){const command=input.value;if(!command)return;const output=document.getElementById(`dsi-console-output`);try{const modifiedCommand=command.replace(/\$signals/g,`DatastarInspector.rootSignals`),result=eval(modifiedCommand);output.innerHTML+=`
                    <div class="dsi-console-line">
                        <span>&gt; ${command}</span>
                    </div>
                    <div class="dsi-console-line dsi-console-result">
                        ${this.formatValue(result)}
                    </div>
                `}catch(e){output.innerHTML+=`
                    <div class="dsi-console-line">
                        <span>&gt; ${command}</span>
                    </div>
                    <div class="dsi-console-line dsi-console-error">
                        Error: ${e.message}
                    </div>
                `}input.value=``,output.scrollTop=output.scrollHeight},copyPath(e){navigator.clipboard.writeText(`$signals.${e}`),this.showStatus(`Path copied to clipboard`)},copyValue(e){let t=this.signals.get(e);navigator.clipboard.writeText(this.formatValue(t)),this.showStatus(`Value copied to clipboard`)},exportSignals(){let e={};this.signals.forEach((t,n)=>{e[n]=t});let t=new Blob([JSON.stringify(e,null,2)],{type:`application/json`}),n=URL.createObjectURL(t),r=document.createElement(`a`);r.href=n,r.download=`datastar-signals-${Date.now()}.json`,r.click(),URL.revokeObjectURL(n),this.showStatus(`Signals exported`)},clearLog(){this.changeLog=[],this.updateChangeLog(),this.showStatus(`Change log cleared`)},showStatus(e){let t=document.getElementById(`dsi-status-text`);t&&(t.textContent=e,setTimeout(()=>{t.textContent=`Ready`},2e3))},minimize(){this.container.classList.add(`dsi-minimized`),this.isVisible=!1,this.adjustPageLayout(this.config.position,!1)},close(){this.container.style.display=`none`,this.adjustPageLayout(this.config.position,!1)},toggle(){this.container.style.display===`none`?(this.container.style.display=``,this.isVisible=!0,this.adjustPageLayout(this.config.position,!0)):this.isVisible?this.minimize():(this.container.classList.remove(`dsi-minimized`),this.isVisible=!0,this.adjustPageLayout(this.config.position,!0))},changePosition(e){this.container&&(this.isVisible&&this.adjustPageLayout(this.config.position,!1),this.container.classList.remove(`dsi-right`,`dsi-left`,`dsi-bottom`),this.container.classList.add(`dsi-${e}`),this.config.position=e,this.isVisible&&this.adjustPageLayout(e,!0),localStorage.setItem(`datastar-inspector-position`,e))},adjustPageLayout(e,t){let n=document.body;[`dsi-push-right`,`dsi-push-left`,`dsi-push-bottom`].forEach(e=>n.classList.remove(e)),t&&!this.container.classList.contains(`dsi-minimized`)&&n.classList.add(`dsi-push-${e}`)},getSignal(e){if(!this.rootSignals)return;let t=e.split(`.`),n=this.rootSignals;for(let e of t){if(!n||!n[e])return;n=n[e]}return typeof n==`function`?n():n},setSignal(e,t){if(!this.rootSignals)return!1;let n=e.split(`.`),r=n.pop(),i=this.rootSignals;for(let e of n){if(!i[e])return!1;i=i[e]}return typeof i[r]==`function`&&i[r](t)}};window.DatastarInspector=DatastarInspector})(window),window.Datastar={root:fe},DatastarInspector.init({position:`right`,width:`400px`,height:`100vh`,theme:`dark`,startMinimized:!0,hotkey:`Shift+D`});