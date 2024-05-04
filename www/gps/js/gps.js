const cfLS='fLS';
const cAD='ad';
const cdFL='dFL';
function gEI(name) { return document.getElementById(name); }
function humanSize(size, iec = true) {
  let base = iec ? 1024 : 1000
  let units = ['', 'K', 'M', 'G', 'T']
  let i = Math.log(size) / Math.log(base) | 0
  return `${(size / Math.pow(base, i)).toFixed(2) * 1} ${units[i]}${i && iec ? 'i' : ''}B`
}
function fLSu(trg) {
	trg.id='';
	ad=gEI(cAD);
	if (ad !== null) {
		pN=ad.parentNode;
		pN.removeChild(ad);
		if (pN.lastChild.tagName == 'P')
			pN.removeChild(pN.lastChild);
	}
	df=gEI(cdFL);
	if (df !== null)
		df.innerText='';
}
function gFN(si) {
	name=si.innerText;
	if (si.parentNode.className != 'fL')
		name=si.parentNode.id.substr(2) + '/' + name;
	return name;
}
function fLS(trg) {
	if (trg.id!='') {
		fLSu(trg);
	} else {
		si=gEI(cfLS);
		if(si!==null)
			fLSu(si);
		trg.id=cfLS;
		df=gEI(cdFL);
		if (df !== null)
			df.innerText=trg.innerText;
		ad=document.createElement('a');
		ad.id=cAD;
		url=window.location.href + '?f=' + gFN(trg);
		ad.href=url+ '&s=';
		ad.download='';
		ad.innerText='...';
		trg.appendChild(ad);

		var req = new XMLHttpRequest();
		req.responseType = 'json';
		req.open('GET', url + '&m=', true);
		req.onload  = function() {
   			var jR = req.response;
			si=gEI(cfLS);
			ad=document.createElement('p');
			txt='size: ' + humanSize(jR.file.size);
			if (jR.gpx !== null) {
				txt+=', gpx v' + jR.gpx.version;
				txt+='<br>start: ' + jR.gpx.time;
				trks=Object.entries(jR.gpx.trks).length;
				txt+='<br>' + jR.gpx.distance.toFixed(2) + ' km, ' + jR.gpx.duration + ', trks #' + trks;
				last=jR.gpx.trks[trks].bounds.last.lat + ', ' + jR.gpx.trks[trks].bounds.last.lon;
				txt+='<br>last: <a href="http://google.de/maps/place/' + encodeURI(last.replace(/\s+/g, '')) + '/@' + encodeURI(last.replace(/\s+/g, '')) + ',15z" target="_blank">' + last + '</a>';
				txt+='<br>time: ' + jR.gpx.lastTime;
			} 
			ad.innerHTML=txt;
			si.appendChild(ad);
		};
		req.send(null);
	}
}
function oc() {
	const trg=event.target;
	if (trg.className == 'fLDL') {
		ul=gEI('ul' + gFN(trg));

		if (ul !== null) {
			ul.style.display=(ul.style.display == 'block' ? 'none' : 'block');
			trg.style.listStyleType=(ul.style.display == 'block' ? 'disclosure-open' : 'disclosure-closed');

			si=gEI(cfLS);
			if (ul.style.display == 'none' && si !== null)
				fLSu(si);
		}
	}
	if (trg.className == '' && trg.nodeName == 'LI')
		fLS(trg);
	if (trg.id == 'overlay') {
	}
}
