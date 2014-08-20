/**
* Growler 1.0.0
*
* Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
* and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
*
* Written by Kevin Armstrong <kevin@kevinandre.com>
* Last updated: 2008.10.14
*
* Growler is a PrototypeJS based class that displays unobtrusive notices on a page.
* It functions much like the Growl (http://growl.info) available on the Mac OS X.
*
*/
/**
* Mirrored from http://code.google.com/p/kproto/
* Re-hosted at https://github.com/jwestbrook/Prototype.Growler
*/
Growler = Class.create({

	initialize: function(options)
	{
		this.noticeOptions = {
			header: 			null,
			speedin: 			0.3,
			speedout: 			0.5,
			outDirection: 		{ y: -20 },
			life: 				5,
			sticky: 			false,
			className: 		""
		};
		this.growlerOptions = {
			location: 			"tr",
			width: 			"250px"
		};
		this.IE = (Prototype.Browser.IE) ? parseFloat(navigator.appVersion.split("MSIE ")[1]) || 0 : 0;

		var opt = Object.clone(this.growlerOptions);
		options = options || {};
		Object.extend(opt, options);
		this.growler = new Element("div", { "class": "Growler", "id": "Growler" });
		this.growler.setStyle({ position: ((this.IE==6)?"absolute":"fixed"), padding: "10px", "width": opt.width, "z-index": "50000" });
		if(this.IE==6)
		{
			var offset = { w: parseInt(this.growler.style.width)+parseInt(this.growler.style.padding)*3, h: parseInt(this.growler.style.height)+parseInt(this.growler.style.padding)*3 };
			switch(opt.location){
				case "br":
					this.growler.style.setExpression("left", "( 0 - Growler.offsetWidth + ( document.documentElement.clientWidth ? document.documentElement.clientWidth : document.body.clientWidth ) + ( ignoreMe2 = document.documentElement.scrollLeft ? document.documentElement.scrollLeft : document.body.scrollLeft ) ) + 'px'");
					this.growler.style.setExpression("top", "( 0 - Growler.offsetHeight + ( document.documentElement.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight ) + ( ignoreMe = document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop ) ) + 'px'");
					break;
				case "tl":
					this.growler.style.setExpression("left", "( 0 + ( ignoreMe2 = document.documentElement.scrollLeft ? document.documentElement.scrollLeft : document.body.scrollLeft ) ) + 'px'");
					this.growler.style.setExpression("top", "( 0 + ( ignoreMe = document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop ) ) + 'px'");
					break;
				case "bl":
					this.growler.style.setExpression("left", "( 0 + ( ignoreMe2 = document.documentElement.scrollLeft ? document.documentElement.scrollLeft : document.body.scrollLeft ) ) + 'px'");
					this.growler.style.setExpression("top", "( 0 - Growler.offsetHeight + ( document.documentElement.clientHeight ? document.documentElement.clientHeight : document.body.clientHeight ) + ( ignoreMe = document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop ) ) + 'px'");
					break;
				default:
					this.growler.setStyle({right: "auto", bottom: "auto"});
					this.growler.style.setExpression("left", "( 0 - Growler.offsetWidth + ( document.documentElement.clientWidth ? document.documentElement.clientWidth : document.body.clientWidth ) + ( ignoreMe2 = document.documentElement.scrollLeft ? document.documentElement.scrollLeft : document.body.scrollLeft ) ) + 'px'");
					this.growler.style.setExpression("top", "( 0 + ( ignoreMe = document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop ) ) + 'px'");
					break;
				}
		}
		else
		{
			switch(opt.location){
				case "br":
					this.growler.setStyle({bottom: 0, right: 0});
					break;
				case "tl":
					this.growler.setStyle({top: 0, left: 0});
					break;
				case "bl":
					this.growler.setStyle({top: 0, right: 0});
					break;
				case "tc":
					this.growler.setStyle({top: 0, left: "25%", width: "50%"});
					break;
				case "bc":
					this.growler.setStyle({bottom: 0, left: "25%", width: "50%"});
					break;
				default:
					this.growler.setStyle({top: 0, right: 0});
					break;
				}
		}
		this.growler.wrap( document.body );
	},
	removeNotice: function(notice, options){
		var opt = Object.clone(this.noticeOptions);
		options = options || {};
		Object.extend(opt, options);
		new Effect.Parallel([
			new Effect.Move(notice, Object.extend({ sync: true, mode: 'relative' }, opt.outDirection)),
			new Effect.Opacity(notice, { sync: true, to: 0 })
		], {
				duration: opt.speedout,
				afterFinish: function(){
					try {
						var noticeexit = notice.down("div.notice-exit");
						if(noticeexit != undefined)
						{
							noticeexit.stopObserving("click", this.removeNotice);
						}
						if(opt.created && Object.isFunction(opt.created))
						{
							notice.stopObserving("notice:created", opt.created);
						}
						if(opt.destroyed && Object.isFunction(opt.destroyed))
						{
							notice.fire("notice:destroyed");
							notice.stopObserving("notice:destroyed", opt.destroyed);
						}
					}
					catch(e){ }
					try {
						notice.remove();
					}
					catch(e){}
				}
			});
	},
	createNotice: function( msg, options)
	{
		var opt = Object.clone(this.noticeOptions);
		options = options || {};
		Object.extend(opt, options);
		var notice = new Element("div", {"class": "Growler-notice "+ opt.className}).setStyle({display: "block", opacity: 0});
		if(opt.created && Object.isFunction(opt.created))
		{
			notice.observe("notice:created", opt.created);
		}
		if(opt.destroyed && Object.isFunction(opt.destroyed))
		{
			notice.observe("notice:destroyed", opt.destroyed);
		}
		if(opt.sticky)
		{
			var noticeExit = new Element("div", {"class": "Growler-notice-exit"}).update("&times;");
			noticeExit.observe("click", function(){ this.removeNotice(notice, opt); }.bind(this));
			notice.insert(noticeExit);
		}
		notice.insert(new Element("div", {"class": "Growler-notice-head"}).update(opt.header));
		notice.insert(new Element("div", {"class": "Growler-notice-body"}).update(msg));
		this.growler.insert(notice);
		new Effect.Opacity(notice, { to: 0.85, duration: opt.speedin });
		if (!opt.sticky)
		{
			this.removeNotice.delay(opt.life, notice, opt);
		}
		notice.fire("notice:created");
		return notice;
	},
	specialNotice: function( msg, options, title, background, color)
	{
		var opt = Object.clone(this.noticeOptions);
		options = options || {};
		Object.extend(opt, options);
		opt.header = opt.header || title;
		var n = this.createNotice( msg, opt);
		n.setStyle({ backgroundColor: background, color: color });
		return n;
	},
	growl: function(msg, options) {
		return this.createNotice( msg, options);
	},
	warn: function(msg, options){
		return this.specialNotice( msg, options, "Warning", "#bd2527", "#FFFFFF");
	},
	error: function(msg, options){
		return this.specialNotice( msg, options, "Error", "#bd2527", "#FFFFFF");
	},
	info: function(msg, options){
		return this.specialNotice( msg, options, "Information", "#2f7bbf", "#FFFFFF");
	},
	ungrowl: function(notice, options){
		this.removeNotice(notice, options);
	}
});