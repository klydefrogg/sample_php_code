function buildFlashAudio(selector, caller) {
	trace("build flashAudio");
	var name = selector.substr(1)+"_";
	$(selector).html("<div id='"+name+"audiofallback'></div>");
	var cachecontrol = Math.floor(Math.random()*99999);
	if ($.browser.msie && parseInt($.browser.version) <= 7) {
		swfobject.embedSWF(b.href+"mp3_player.swf?c"+cachecontrol, name+'audiofallback', "1px", "1px", "9.0.280", null, {parentName:caller.varName}, {wmode:"transparent",bgcolor:"#FFFFFF",allowscriptaccess:"always",allowfullscreen:"true"}, {id:name+"audiofallback"})
	
	} else {
	
		$("#"+name+"audiofallback").flash({
				id:name+"audiofallback",
				width:"1px",
				height:"1px",
				src:b.href+"mp3_player.swf?c"+cachecontrol,
				wmode:"opaque",
				bgcolor:"#FFFFFF",
				allowscriptaccess:"always",
				allowfullscreen:"true",
				version:"9.0.280",
				flashvars: {parentName: caller.varName},
				express:null
			});
	}
	caller.setReference($("#"+name+"audiofallback").get(0));
	if (caller.src!="") caller.playFile(caller.src);
}

function Audio(selector, varName) {
	this.isVO=false;
	this.initialized=false;
	this.varName = varName;
	this.player=false;
	this.isMuted=false;
	this.isReady=false;
	this.src="";
	this.repeat = false;
	if (selector=="#vo") {
		this.isVO = true;
	}
	this.ref=$(selector);
	this._base = this;
	
	this.flashInit=function(){
		this.initialized=true;
		if (this.src!="") {
			this.playFile(this.src, this.repeat);
		}
	}
	
	this.init=function() {
		trace('audio init');
		trace(this.isVO);
		if (this.isVO) {
			eventDispatcher.off('audio_muted',this.mute).on('audio_muted',this.mute, this);
			eventDispatcher.off('audio_unmuted',this.unmute).on('audio_unmuted',this.unmute, this);
		
			eventDispatcher.on('product_information_ready', function() {
				if (productExpanded.product.showMuteButton===false) {
					$('#mute_toggle').remove();
					return;
				} else {
					var display = "inline-block";
					//if ($.browser.msie && $.browser.version <= 9) display = "inline";
					$('#mute_toggle').fadeIn('fast').css('display',display);
				}
			});
		
			$('#mute_toggle').off('click').on('click',function(){
				if ($(this).toggleClass('muted').hasClass('muted')) {
					//audio.toggleMute();
					eventDispatcher.trigger('audio_muted');
				} else {
					eventDispatcher.trigger('audio_unmuted');
				}
			});
		} else {
			
			eventDispatcher.off('audio_muted',this.masterMute).on('audio_muted',this.masterMute, this);
			eventDispatcher.off('audio_unmuted',this.masterUnmute).on('audio_unmuted',this.masterUnmute, this);
			
			eventDispatcher.off('music_muted', this.masterMute).on('music_muted', this.mute, this);
			eventDispatcher.off('music_unmuted', this.masterUnmute).on('music_unmuted', this.unmute, this);
			
			$('#music_toggle').off('click').on('click',function(){
				if(!audio.isMuted) {
					if ($(this).toggleClass('muted').hasClass('muted')) {
						//audio.toggleMute();
						eventDispatcher.trigger('music_muted');
					} else {
						eventDispatcher.trigger('music_unmuted');
					}
				}
			});
		}
		
		
		if (this.doHtml5Audio()) {
			this.player = new HTML5Audio(this);
			trace("HTML5 Audio");
			this.initialized=true;	
		} else {
			this.player = new FlashAudio(this);
			trace("FLASH AUDIO");
		}
		
		this.player.init();
	}
	this.kill=function() {
		trace("KILL");
		trace(this.player);
		this.src="";
		this.player.stopAll();
	}
	
	this.playFile=function(src, repeat) {
		if (this.initialized) {
			var volume = .05;
			if (this.isVO) volume = 1;
			if (repeat==true) this.repeat = true;
			else this.repeat = false;
			this.src=src;
			this.player.setSrc(src, volume, this.repeat);
			
			if (!this.isVO) {
				var display = "inline-block";
				//if ($.browser.msie && $.browser.version <= 9) display = "inline";
				$('#music_toggle').fadeIn('fast').css('display',display);
			}
		} else {
			this.src=src;
			this.repeat=(repeat==true)?true:false;
		}
	}
	this.playbackState=function(state) {
		//paused,playing,source_set	
		if (state=="paused") {
			//audio.mute();
		}
		if (state=="playing") {
			//audio.unmute();
		}
		if (state=="source_set") {
			this.isReady = true;
			this.player.ready();
		}
	}
	//this is only called if audio is non-vo
	this.masterUnmute=function() {
		if (!this.isMuted) {
			$('#music_toggle').removeClass('muted');
			try{
				this.player.resume();	
			} catch(e) {
				
			}
		}
	}
	this.masterMute = function() {
		try{
			$('#music_toggle').addClass('muted');
			this.player.pause();
		} catch (e) {
			
		}
	}
	
	this.mute=function(e) {
		this.isMuted = true;
		try{
			this.player.pause();
		} catch(e) {}
		//$('#mute_toggle').addClass('muted');
	}
	this.unmute=function(e) {
		trace("unmute");
		this.isMuted = false;
		try{
			this.player.resume();
		} catch(e) {
			trace("error unmuting audio");
			trace(e);
		}
		//$('#mute_toggle').removeClass('muted');
	}
	this.toggleMute=function() {
		this.player.playPause();
	}
	this.volume = function(vol) {
		if (vol==undefined) {
			return this.player.volume();
		} else {
			this.player.volume(vol);
		}
	}
	this.setReference=function(obj) {
		this.player.ref = obj;
	}
	this.playbackComplete=function() {
		trace("PLAYBACK COMPLETE");
		if (this.isVO) {
			eventDispatcher.trigger("vo_playback_complete");
		}
	}
	this.setDuration=function(s){
		if (this.isVO && s!=0) {
			eventDispatcher.trigger("playback_start");
			trace("SET VO duration "+s);
			display.setCurrentMediaDuration(s);
		}
	}
}
Audio.prototype.doHtml5Audio = function() {
		
		var a = document.createElement('audio');
		return !!(a.canPlayType && a.canPlayType('audio/mpeg;').replace(/no/, ''));
}
function FlashAudio (parent) {
	this.parent = parent;
	this.type="flash";
	this.ref=false;
	
	this.init=function() {
		if (this.parent.isVO) {
			buildFlashAudio("#vo", this.parent);
		} else {
			buildFlashAudio("#music", this.parent);
		}
	}
	this.setSrc=function(src, volume, loop) {
		trace("src: "+src+", volume: "+volume+", Loop: "+loop);
		if (this.ref) {
			//if (!this.parent.isVO) volume = volume/2;
			//this.ref.setReferenceName(this.parent.varName);
			this.ref.setSrc(src, volume, loop);
		}
	}
	this.ready=function(){
		if (!audio.isMuted) {
			this.play();	
		}
	}
	this.resume=function(){
		trace("RESUME");
		this.ref.resume();
	}
	this.pause=function(){
		trace("PAUSE");
		this.ref.soundPause();
	},
	this.play=function() {
		this.ref.startPlayback();
	},
	this.playPause=function(){
		this.ref.playPause();
	},
	this.stopAll=function(){
		if (this.ref) try { this.ref.stopPlayback(); } catch(e) {}
		//flashAudio.ref.setSrc("");
	}
	//repeat
}
function HTML5Audio (parent) {
	this.parent = parent;
	this.type="html5";
	this.ref=false;
	this.setSrc=function(src, volume) {
		if (src!=this.ref.src) {
			this.parent.isReady = false;
			if (this.parent.repeat==true) {
				$(this.ref).attr('loop','loop');
			} else {
				$(this.ref).removeAttr('loop');
			}
			this.ref.src = src;
		}
		this.volume(volume);
	}
	this.ready=function(){
		if (!audio.isMuted) {
			this.ref.play();
		}
		this.parent.setDuration(this.ref.duration);
	}
	this.resume=function(){
		this.ref.play();
	},
	this.pause=function(){
		this.ref.pause();
	},
	this.playPause=function(){
		trace("playpause");
		if (this.ref.paused) {// || (!html5Audio.ref.paused && audio.isReady)) {
			this.ref.play();
			this.parent.playbackState('playing');
		} else {
			this.ref.pause();
			this.parent.playbackState('paused');
		}
	},
	this.stopAll=function() {
		this.ref.src="";
		this.ref.load();
		
	},
	this.volume=function(vol) {
		if (vol==undefined) {
			return this.ref.volume*100;
		} else {
			this.ref.volume=vol;
		}
	}
}
HTML5Audio.prototype.init = function() {
	this.ref = $('audio', this.parent.ref).get()[0];
	var self = this;
	this.ref.addEventListener(
		'canplay',
		function(){
			self.parent.playbackState("source_set");
		},
		false);	

	this.ref.addEventListener(
		'ended',
		function(){
			self.parent.playbackComplete();
		},
		false);
}

var audio = false;//new Audio("#vo");
var music = false;//new Audio("#music");

eventDispatcher.on('dom_ready', function(){
	audio = new Audio("#vo", "audio");
	audio.init.apply(audio);
	music = new Audio("#music", "music");
	music.init.apply(music);
});//audio.init, audio);
eventDispatcher.on('product_information_ready',function() {
	if (productExpanded.product.trainingBackgroundMusicUrl!="") {
		music.playFile(productExpanded.product.trainingBackgroundMusicUrl, true)
	}
});
