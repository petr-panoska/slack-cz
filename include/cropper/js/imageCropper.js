/* Javascript Image Cropper 

Copyright (C) 2005 Josef ©íma

Tato knihovna je svobodný software; mù¾ete ji ¹íøit a upravovat
v souladu s podmínkami GNU Lesser General Public License,
tak jak ji vydala nadace Free Software Foundation; buï verze 2.1
této licence, anebo (dle svého uvá¾ení) kterékoli pozdìj¹í verze.

Tato knihovna je ¹íøena v nadìji, ¾e bude u¾iteèná, av¹ak
BEZ JAKÉKOLI ZÁRUKY; dokonce i bez pøedpokládané záruky
OBCHODOVATELNOSTI èi VHODNOSTI PRO URÈITÝ ÚÈEL.
Pro dal¹í podrobnosti viz GNU Lesser General Public License.

Spolu s touto knihovnou jste mìli obdr¾et kopii GNU Lesser General
Public License; pokud se tak nestalo, pi¹te na Free Software Foundation,
Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

CHANGE LOG:
1.1.5:
	- podpora min rozmeru, napr.: imageCropper.addCroper('obrazek','100-200','200','fixed','0f0');
	- pridano zobrazeni rozmeru aktualniho vyrezu
	
1.1.4:
	- kod z vetsi casti prepracovan, aby umoznoval pouzit cropper na vice obrazich v jedne strance
	- tvorba hidden promenych obsahuje i ID obrazku
	
1.1.3:
	- opraven bug se z-indexem u jednotlivych ramecku
	
1.1.2:
	- pridana moznost urcovani barvy ramecku
	
1.1.1:
	- vychozi verze

verze 1.1.5, 11.8.2005

Posledni verze k dispozici na adrese:
http://www.chose.cz/image-cropper/
*/

var imageCropper = {

	addCroper : function (id,width,height,ratio,bColor) {
		// zjistime, zda obrazek existuje a pokracujeme
		var x = dom.gI(id);
		if ((typeof(x)!="undefined") && (x.nodeName.toLowerCase() == "img")) {
			
			y = ( (typeof(x.counter) == "undefined")) ? 0 : (x.counter+1);
						
			if (navigator.userAgent.indexOf('MSIE 5') == -1) x.fixBM = 0;
			else x.fixBM = 2;
			
			x.counter = y;
			x.lastZIndex = y;
			
			// nastavime pruhlednost
			imageCropper._setOpacity(x,30);
			
			// vytvori konteiner na obrazek
			imageCropper._makeHolder(x);
			
			// vytvorime samotny orezavaci div
			c = imageCropper._makeBox(x,width,height,ratio,bColor);
			
			// vytvorime do formulare promenne
			imageCropper._makeHiddens(x,width,height);
			
			// aktualizujeme rozmeny v hidden
			imageCropper._updateHiddens(c);
			
			return false;
		}
	},
	
	useForm : function (frm) {
		if (typeof(document.forms[frm]) == "object") {
			imageCropper.form = document.forms[frm];
		} else {
			alert('Nenalezen formular');
		}
	},
	
	_makeHolder : function (x) {
		if (!x.holder) {
			var holder = dom.cE('div');
			holder.style.position = 'relative';
			holder.style.width = x.width+'px';
			holder.style.height = x.height+'px';
			holder.style.backgroundColor = '#000';
			x.holder = holder;
			dom.aC(x.parentNode,holder);
			dom.aC(holder,x);
		}
	},
	
	_makeBox : function (x,width,height,ratio,bColor) {
		y = x.counter;
		
		var c = dom.cE('div');
		c.imageObject = x;
		c.boxId = y;
		
		c.width = (parseInt(width)>0) ? parseInt(width) : 60;
		c.height = (parseInt(height)>0) ? parseInt(height) : 45;
		c.resRatio = (ratio) ? ratio : 'auto';
		
		// zjistime, zda nemame dynamic rozmery
		if (width.indexOf('-') != -1) {
			var d = width.split('-');
			c.width = c.widthMin = parseInt(d[0]);
			c.widthMax = parseInt(d[1]);
		}
		
		if (height.indexOf('-') != -1) {
			var d = height.split('-');
			c.height = c.heightMin = parseInt(d[0]);
			c.heightMax = parseInt(d[1]);
		}
		
		c.posX = 0;
		c.posY = 0;
		c.ratio = c.width / c.height;
		c.bColor = (bColor) ? bColor : 'fff';
		c.tmpNewH = c.height;
		c.tmpNewW = c.width;
		c.tmpPosX = c.posX;
		c.tmpPosY = c.posY;
		
		c.className = 'cropBox';
		c.style.position = 'absolute';
		c.style.width = c.width+(x.fixBM)+'px';
		c.style.height = c.height+(x.fixBM)+'px';
		c.style.background = 'transparent url('+x.src+') -'+(c.posX+1)+'px -'+(c.posY+1)+'px no-repeat';
		c.style.cursor = 'move';
		c.style.left = c.posX+'px';
		c.style.top = c.posY+'px';
		c.style.overflow = 'visible';
		c.style.border = '1px solid #'+c.bColor;
		c.style.zIndex = x.lastZIndex;
		c.offX = 0;
		c.offY = 0;
			
		// vlozime do rodice obrazku celej hotovej cropBox
		dom.aC(x.parentNode,c);
		
		// informace o velikosti vyrezu
		var cD = dom.cE('div');
		cD.innerHTML = c.width+'x'+c.height;
		cD.style.position = 'absolute';
		cD.style.top = '-1.5em';
		cD.style.lineHeight = '1em';
		cD.style.left = '-1px';
		cD.style.border = '1px solid #'+c.bColor;
		cD.style.display = 'inline';
		cD.style.padding = '0.2em';
		cD.style.fontSize = '89%';
		cD.style.color = '#'+c.bColor;
		dom.aC(c,cD);
		c.dimObject = cD; 
		
		// resizovaci smery
		var dirs = new Array(
			new Array('e','45%','100%'),
			new Array('se','100%','100%'),
			new Array('s','100%','45%')
		);
		
		// vytvorime potrebne divy
		for (i=0; i<dirs.length; i++) {
			var r = dom.cE('div');
			r.style.position = 'absolute';
			r.className = 'resize';
			r.style.width='9px';
			r.style.height='9px';
			r.style.top = dirs[i][1];
			r.style.left = dirs[i][2];
			r.style.overflow = 'hidden';
			r.style.background='transparent url(img/r-'+c.bColor+'-'+dirs[i][0]+'.gif) 0 0 no-repeat';
			r.style.cursor = dirs[i][0]+'-resize';
			r.direction = dirs[i][0];
			
			dom.aE(r,"mousedown",imageCropper._prepareResize,false);
			dom.aC(c,r);
			
		}
		
		// EVENT - stisknuti tlacitka na presun
		dom.aE(c,"mousedown",imageCropper._mouseDown,false);
		
		return c;
	},
	
	_makeHiddens : function (x,width,height) {
		var picId = x.id;
		
		// vytvorime objekty
		if (!imageCropper.hiddens) imageCropper.hiddens = new Object; 
		if (!imageCropper.hiddens[x.id]) imageCropper.hiddens[x.id] = new Object;
		
		imageCropper.hiddens[x.id][x.counter] = new Object;
		
		var crop_variables = new Array('x1','y1','x2','y2');
		for (y in crop_variables) {
			z = dom.cE('input');
			z.type = 'hidden';
			z.name = 'crop['+x.id+']['+x.counter+']['+(crop_variables[y])+']';
			z.value = 0;			
			imageCropper.hiddens[x.id][x.counter][(crop_variables[y])] = z;
			dom.aC(imageCropper.form,z);
		}
		
		var z = dom.cE('input');
		z.type = 'hidden';
		z.name = 'crop['+x.id+']['+x.counter+'][w]';
		z.value = width;
		imageCropper.hiddens[x.id][x.counter]['w'] = z;
		dom.aC(imageCropper.form,z);
		
		var z = dom.cE('input');
		z.type = 'hidden';
		z.name = 'crop['+x.id+']['+x.counter+'][h]';
		z.value = height;
		imageCropper.hiddens[x.id][x.counter]['h'] = z;
		dom.aC(imageCropper.form,z);
		
	},
	
	_prepareResize : function (e) {
		
		// osetrime vzniklou udalost
		ev = dom.fE(e);
		
		c = imageCropper._getActiveImage(ev);
		imageCropper.activeC = c;
				
		ev.cancelBubble = true;
		if (ev.stopPropagation) ev.stopPropagation();
		
		var target = (window.event) ? ev.srcElement : ev.target;
		c.resDir = target.direction;
		
		c.relResize = new Object;
		c.relResize.mouseOrigX = ev.clientX;
		c.relResize.mouseOrigY = ev.clientY;
		c.style.borderStyle = 'dashed';
		c.dimObject.style.borderStyle = 'dashed';
		c.style.zIndex=(c.imageObject.lastZIndex++);
		
		// navesime udalosti
		dom.aE(document,"mousemove",imageCropper._resizeBox,false);
		dom.aE(document,"mouseup",imageCropper._resizeBoxEnd,false);
		
		return false;
	},
	
	_resizeBox : function (e) {
		ev = dom.fE(e);
		var mX = ev.clientX;
		var mY = ev.clientY;
		
		c = imageCropper.activeC;
		i = c.imageObject;
		 
		var relW = mX - c.relResize.mouseOrigX;
		var relH = mY - c.relResize.mouseOrigY;
		
		if ( (c.resDir == "n") || (c.resDir == "s") ) var relW = 0;
		if ( (c.resDir == "e") || (c.resDir == "w") ) var relH = 0;
		
		var newX = c.posX;
		var newY = c.posY;
		
		var newW = c.width+relW;
		var newH = c.height+relH;
		
		var fixedResize = ( (c.resRatio == "fixed") || (ev.shiftKey) ) ? true : false;
		
		// zvetsovani se shiftem zachovava pomer
		if ( fixedResize == true ) {
			if ( (c.resDir != "e") && (c.resDir != "w") ) {
				newW = (newH*(c.width/c.height));
			} else  if ( (c.resDir != "n") && (c.resDir != "s") ) {
				newH = (newW*(c.height/c.width));
			}
		}
		
		if ((newW<50) || (c.widthMin && newW < c.widthMin)) {
			newW = c.widthMin ? c.widthMin : 50;
			newH = ( fixedResize == true ) ? (newW*(c.height/c.width)) : newH;
		} else if (newW+newX > i.width) {
			newW = i.width-newX;
			newH = ( fixedResize == true ) ? (newW*(c.height/c.width)) : newH;
		}
		
		if ((newH<50) || (c.heightMin && newH < c.heightMin)) {
			newH = c.heightMin ? c.heightMin : 50;
			newW = ( fixedResize == true ) ? (newH*(c.width/c.height)) : newW;
		} else if (newH+newY > i.height) {
			newH = i.height-newY;
			newW = ( fixedResize == true ) ? (newH*(c.width/c.height)) : newW;
		}
		
		newW = Math.round(newW);
		newH = Math.round(newH)
		
		c.style.width = newW+'px';
		c.style.height = newH+'px';
		c.dimObject.innerHTML = newW+'x'+newH;
		return false;
	},
	
	_resizeBoxEnd : function (e) {
		
		cTemp = imageCropper._getActiveImage(ev);
		if (cTemp) c = cTemp;
		else c = c;
		var i = c.imageObject;
		
		if (c.relResize) {
			c.width = parseInt(c.style.width);
			c.height = parseInt(c.style.height);
			c.posX = parseInt(c.style.left);
			c.posY = parseInt(c.style.top);
			c.style.borderStyle = 'solid';
			c.dimObject.style.borderStyle = 'solid';
			c.relResize = null;
		}
		
		imageCropper._updateHiddens(c);
		
		dom.rE(document,"mousemove",imageCropper._resizeBox,false);
		dom.rE(document,"mouseup",imageCropper._resizeBoxEnd,false);
	},
	
	_setOpacity : function (o,opacity) {
		o.style.filter = "alpha(opacity:"+opacity+",style=0)";
		o.style.KHTMLOpacity = opacity/100;
		o.style.MozOpacity = opacity/100;
		o.style.opacity = opacity/100;
	},
	
	_mouseDown : function (e) {
		
		ev = dom.fE(e);
		var mX = ev.clientX;
		var mY = ev.clientY;
		
		c = imageCropper._getActiveImage(ev);
		
		imageCropper.activeC = c;
		
		dom.aE(document,"mousemove",imageCropper._dragBox,false);
		dom.aE(document,"mouseup",imageCropper._mouseUp,false);
		
		// nastavime aktual souradnice
		c.style.top = parseInt((c.style.top) ? parseInt(c.style.top) : 0)+'px';
		c.style.left = parseInt((c.style.left) ? parseInt(c.style.left) : 0)+'px';
		c.style.zIndex=(c.imageObject.lastZIndex++);
		c.style.borderStyle = 'dashed';
		c.dimObject.style.borderStyle = 'dashed';
		
		c.offY = parseInt(c.style.top)-mY;
		c.offX = parseInt(c.style.left)-mX;
		
		return false;
	},
	
	_dragBox : function (e) {
		c = imageCropper.activeC;
		
		ev = dom.fE(e);
		var mX = ev.clientX;
		var mY = ev.clientY;
		
		x = c.imageObject;

		newX = mX+c.offX;
		newY = mY+c.offY;
		
		if (newY<0) newY = 0;
		if (newX<0) newX = 0;
		
		if (newY > (x.height - c.height) ) newY = x.height - (c.height);
		if (newX > (x.width - c.width) ) newX = x.width - (c.width);
		
		// thanks; idea by Vogel (Petr Dolezal)
		c.style.backgroundPosition = '-'+(newX+1)+'px -'+(newY+1)+'px';
	
		c.posX = newX;
		c.posY = newY;
		c.style.top = newY+'px';
		c.style.left = newX+'px';
		
		return false;
	},
	_mouseUp : function (targ) {
		
		dom.rE(document,"mousemove",imageCropper._dragBox,false);
		dom.rE(document,"mouseup",imageCropper._mouseUp,false);
		
		cTemp = imageCropper._getActiveImage(ev);
		if (cTemp) c = cTemp;
		else c = c;
		
		x = c.imageObject;
		c.style.borderStyle = 'solid';
		c.dimObject.style.borderStyle = 'solid';
		imageCropper._updateHiddens(c);
		return false;
	},
	
	_updateHiddens : function (c) {
		x = c.imageObject.id;
		y = c.boxId;
		
		imageCropper.hiddens[x][y]['x1'].value = c.posX;
		imageCropper.hiddens[x][y]['y1'].value = c.posY;
		imageCropper.hiddens[x][y]['x2'].value = (c.posX+c.width);
		imageCropper.hiddens[x][y]['y2'].value = (c.posY+c.height);
		if (c.resRatio != "fixed") {
			imageCropper.hiddens[x][y]['w'].value = c.width;
			imageCropper.hiddens[x][y]['h'].value = c.height;
		} else if (c.resRatio == "fixed") {
			imageCropper.hiddens[x][y]['w'].value = (c.widthMax) ? c.widthMin+'-'+c.widthMax : imageCropper.hiddens[x][y]['w'].value;
			imageCropper.hiddens[x][y]['h'].value = (c.heightMax) ? c.heightMin+'-'+c.heightMax : imageCropper.hiddens[x][y]['h'].value;
		}
		
		return true;
	},
	_getActiveImage : function (event) {
		var target = (window.event) ? ev.srcElement : ev.target;
		var done = false;
		var x = 0;
		
		while(done == false) {
			if (target.imageObject) {
				done == true;
				return target;
			} else if (target.parentNode) {
				target = target.parentNode;
			} else {
				return false;
			}
		}
	},
	_getActiveId : function (event) {
		var target = (window.event) ? ev.srcElement : ev.target;
		if (target.boxId >= 0) {
			return target.boxId;
		} else {
			return target.parentNode.boxId;
		}
	}
}

var dom = {
	gI : function (el) {
		return document.getElementById(el);
	},
	cT : function(txt) {
		return document.createTextNode(txt);
	},
	cE : function (el) {
		return document.createElement(el);
	},
	aC : function (p,c) {
		return p.appendChild(c);
	},
	aE : function (elm,evtType,evtFn,set) {
		if (document.addEventListener) {
			if ((elm == window) && window.opera){
				elm == document;
			} 
			elm.addEventListener(evtType,evtFn,set);
		} else {
			elm.attachEvent('on' + evtType,evtFn);
		}
	},
	rE : function (elm,evtType,evtFn,set) {
		if (document.addEventListener) {
			if ((elm == window) && window.opera) elm == document;
			elm.removeEventListener(evtType,evtFn,set);
		} else {
			elm.detachEvent('on' + evtType,evtFn);
		}
	},
	fE : function(e) {
		if (typeof e == 'undefined') e = window.event;
		return e;
	}
}
