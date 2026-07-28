/**
 * MetaHotels — Video Background (R2) for Elementor.
 *
 * Reads the JSON config from a section/container wrapper's data-vbg attribute
 * and builds the `.vbg-layer` client-side. Three sources share one control bar:
 *   - file:    a self-hosted <video> (MP4)
 *   - youtube: a chrome-suppressed YouTube IFrame player
 *   - wistia:  a chrome-suppressed Wistia player (E-v1.js + the _wq queue)
 * The control bar drives any of them through a small adapter object.
 *
 * Handles muted autoplay, sound-unlock on gesture, lazy loading, off-screen
 * pause/resume, sound persistence, reduced-motion, and live editor rebuilds.
 *
 * Vanilla, ES5, IIFE-scoped. Fails silently; never throws.
 */
(function () {
	'use strict';

	var SOUND_KEY = 'vbg-sound';

	var GLYPHS =
		'<svg class="vbg-play__pause" viewBox="0 0 11 13" aria-hidden="true" focusable="false">' +
		'<rect x="0" y="0" width="4" height="13"></rect><rect x="7" y="0" width="4" height="13"></rect></svg>' +
		'<svg class="vbg-play__play" viewBox="0 0 11 13" aria-hidden="true" focusable="false">' +
		'<path d="M0 0 L11 6.5 L0 13 Z"></path></svg>';

	// Shared, single-load promise for the YouTube IFrame API.
	var ytApiPromise = null;

	function prefersReducedMotion() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function isMobileViewport() {
		return !!(window.matchMedia && window.matchMedia('(max-width: 767px)').matches);
	}

	function getStoredSound() {
		try {
			return window.sessionStorage.getItem(SOUND_KEY) === 'on';
		} catch (e) {
			return false;
		}
	}

	function setStoredSound(on) {
		try {
			window.sessionStorage.setItem(SOUND_KEY, on ? 'on' : 'off');
		} catch (e) {}
	}

	function hasClass(el, name) {
		return el && (' ' + el.className + ' ').indexOf(' ' + name + ' ') !== -1;
	}

	function removeChildLayer(el) {
		var kids = el.children;
		var i;
		for (i = 0; i < kids.length; i++) {
			if (hasClass(kids[i], 'vbg-layer')) {
				el.removeChild(kids[i]);
				return;
			}
		}
	}

	function chooseSource(cfg, mobile) {
		if (mobile && cfg.mobileBehavior === 'poster') {
			return '';
		}
		if (mobile) {
			return cfg.mobile || cfg.desktop || '';
		}
		return cfg.desktop || '';
	}

	function setPaused(ctx, paused) {
		if (paused) {
			if (!hasClass(ctx.layer, 'is-paused')) {
				ctx.layer.className += ' is-paused';
			}
		} else {
			ctx.layer.className = ctx.layer.className
				.replace(/(^|\s)is-paused(?=\s|$)/g, ' ')
				.replace(/\s+/g, ' ')
				.replace(/^\s|\s$/g, '');
		}
		if (ctx.playBtn && ctx.i18n) {
			ctx.playBtn.setAttribute('aria-label', paused ? ctx.i18n.play : ctx.i18n.pause);
		}
	}

	function setReady(ctx) {
		if (!hasClass(ctx.layer, 'is-ready')) {
			ctx.layer.className += ' is-ready';
		}
	}

	function setSwitch(ctx, on) {
		if (ctx.switchEl) {
			ctx.switchEl.setAttribute('aria-checked', on ? 'true' : 'false');
		}
		// The pill's caption states the CURRENT sound state, so it has to track
		// the switch. Leaving it fixed makes it read "OFF" while audio plays.
		if (ctx.switchTextEl) {
			ctx.switchTextEl.textContent = on
				? (ctx.i18n.on || 'ON')
				: (ctx.i18n.off || 'OFF');
		}
	}

	// ---- Control bar (shared, source-agnostic via ctx.adapter) ----------

	function onSoundToggle(ctx) {
		if (!ctx.adapter) {
			return;
		}
		var next = !ctx.wantSound;
		ctx.wantSound = next;
		setSwitch(ctx, next);
		setStoredSound(next);
		if (next) {
			// Turning sound ON is the gesture that unlocks audio; if the media
			// is paused we MUST resume it synchronously in this handler.
			ctx.adapter.unmute();
			ctx.autoResume = true;
			ctx.userPaused = false;
			if (ctx.adapter.isPaused()) {
				ctx.adapter.play();
			}
			setPaused(ctx, false);
		} else {
			// Turning sound OFF leaves playback running, just muted.
			ctx.adapter.mute();
		}
	}

	function onPlayToggle(ctx) {
		if (!ctx.adapter) {
			return;
		}
		if (ctx.adapter.isPaused()) {
			ctx.userPaused = false;
			ctx.autoResume = true;
			ctx.adapter.play();
			setPaused(ctx, false);
		} else {
			ctx.userPaused = true;
			ctx.adapter.pause();
			setPaused(ctx, true);
		}
	}

	function buildControls(ctx) {
		var controls = document.createElement('div');
		controls.className = 'vbg-layer__controls is-pos-' + ctx.cfg.position;

		// Play / pause glyph button.
		var playBtn = document.createElement('button');
		playBtn.type = 'button';
		playBtn.className = 'vbg-play';
		playBtn.innerHTML = GLYPHS;
		playBtn.setAttribute('aria-label', ctx.i18n.pause);
		playBtn.onclick = function () {
			onPlayToggle(ctx);
		};
		ctx.playBtn = playBtn;

		// Uppercase label.
		var label = document.createElement('span');
		label.className = 'vbg-layer__label';
		label.appendChild(document.createTextNode(ctx.cfg.label || ''));

		// Pill sound switch.
		var sw = document.createElement('button');
		sw.type = 'button';
		sw.className = 'vbg-switch';
		sw.setAttribute('role', 'switch');
		sw.setAttribute('aria-label', ctx.cfg.label || '');
		var swText = document.createElement('span');
		swText.className = 'vbg-switch__text';
		var swKnob = document.createElement('span');
		swKnob.className = 'vbg-switch__knob';
		sw.appendChild(swText);
		sw.appendChild(swKnob);
		sw.onclick = function () {
			onSoundToggle(ctx);
		};
		ctx.switchEl = sw;
		ctx.switchTextEl = swText;
		// Paint the starting state through the same path the toggle uses. Sound
		// may already be on from the stored per-session preference, so this is
		// not always the off state.
		setSwitch(ctx, ctx.wantSound);

		controls.appendChild(playBtn);
		controls.appendChild(label);
		controls.appendChild(sw);
		return controls;
	}

	// ---- File (<video>) source ------------------------------------------

	function ensureSrc(ctx) {
		if (ctx.srcAssigned || !ctx.video || !ctx.src) {
			return;
		}
		ctx.srcAssigned = true;
		ctx.video.src = ctx.src;
		try {
			ctx.video.load();
		} catch (e) {}
	}

	function playSilently(video) {
		var p;
		try {
			p = video.play();
		} catch (e) {
			p = null;
		}
		return p;
	}

	// Attempt playback honoring the desired sound state, falling back to muted
	// if unmuted autoplay is blocked by the browser.
	function tryAutoplay(ctx) {
		if (!ctx.video) {
			return;
		}
		ensureSrc(ctx);
		ctx.video.muted = !ctx.wantSound;
		var p = playSilently(ctx.video);
		if (p && typeof p.then === 'function') {
			p.then(function () {
				setPaused(ctx, false);
			})['catch'](function () {
				if (ctx.wantSound) {
					// Unmuted autoplay refused: recover muted.
					ctx.wantSound = false;
					setSwitch(ctx, false);
					ctx.video.muted = true;
					var p2 = playSilently(ctx.video);
					if (p2 && typeof p2.then === 'function') {
						p2.then(function () {
							setPaused(ctx, false);
						})['catch'](function () {
							setPaused(ctx, true);
						});
					}
				} else {
					setPaused(ctx, true);
				}
			});
		}
	}

	function setupLoadObserver(ctx) {
		function start() {
			ensureSrc(ctx);
			if (ctx.autoResume) {
				tryAutoplay(ctx);
			}
		}
		if (!('IntersectionObserver' in window)) {
			start();
			return;
		}
		var io = new IntersectionObserver(function (entries) {
			var i;
			for (i = 0; i < entries.length; i++) {
				if (entries[i].isIntersecting) {
					start();
					io.disconnect();
					return;
				}
			}
		}, { rootMargin: '200px' });
		io.observe(ctx.section);
		ctx.teardowns.push(function () {
			io.disconnect();
		});
	}

	function setupVisibilityObserver(ctx) {
		if (!('IntersectionObserver' in window)) {
			return;
		}
		var io = new IntersectionObserver(function (entries) {
			var i, e;
			for (i = 0; i < entries.length; i++) {
				e = entries[i];
				if (e.isIntersecting) {
					// Resume only if allowed and not deliberately paused.
					if (ctx.autoResume && !ctx.userPaused && ctx.video.paused) {
						playSilently(ctx.video);
						setPaused(ctx, false);
					}
				} else if (!ctx.video.paused) {
					ctx.video.pause();
					setPaused(ctx, true);
				}
			}
		}, { threshold: 0.01 });
		io.observe(ctx.section);
		ctx.teardowns.push(function () {
			io.disconnect();
		});
	}

	// Build the <video>, wire lazy load / autoplay / off-screen pause, and
	// return the control adapter. Returns null for poster-only (no source).
	function initFile(section, layer, cfg, reduce, mobile, ctx) {
		var src = chooseSource(cfg, mobile);
		if (!src) {
			return null;
		}

		var video = document.createElement('video');
		video.className = 'vbg-layer__video';
		video.muted = true;
		video.loop = true;
		video.playsInline = true;
		video.setAttribute('muted', '');
		video.setAttribute('playsinline', '');
		video.setAttribute('webkit-playsinline', '');
		video.preload = 'none';
		video.tabIndex = -1;
		if (cfg.poster) {
			video.setAttribute('poster', cfg.poster);
		}
		layer.appendChild(video);

		ctx.video = video;
		ctx.src = src;
		ctx.srcAssigned = false;

		video.addEventListener('loadeddata', function () {
			setReady(ctx);
		});
		video.addEventListener('playing', function () {
			setReady(ctx);
		});

		if (reduce) {
			// Reduced motion: render paused on the poster, play available.
			setReady(ctx);
			setPaused(ctx, true);
			ensureSrc(ctx);
		} else {
			setupLoadObserver(ctx);
		}
		setupVisibilityObserver(ctx);

		return {
			play: function () {
				ensureSrc(ctx);
				return playSilently(ctx.video);
			},
			pause: function () {
				try {
					ctx.video.pause();
				} catch (e) {}
			},
			mute: function () {
				ctx.video.muted = true;
			},
			unmute: function () {
				ctx.video.muted = false;
			},
			isPaused: function () {
				return ctx.video.paused;
			}
		};
	}

	// ---- YouTube source -------------------------------------------------

	function loadYouTubeApi() {
		if (ytApiPromise) {
			return ytApiPromise;
		}
		ytApiPromise = new Promise(function (resolve) {
			if (window.YT && window.YT.Player) {
				resolve();
				return;
			}
			// Chain any previously-registered handler rather than clobber it.
			var prev = window.onYouTubeIframeAPIReady;
			window.onYouTubeIframeAPIReady = function () {
				if (typeof prev === 'function') {
					try {
						prev();
					} catch (e) {}
				}
				resolve();
			};
			if (!document.getElementById('vbg-yt-api')) {
				var tag = document.createElement('script');
				tag.id = 'vbg-yt-api';
				tag.src = 'https://www.youtube.com/iframe_api';
				// On load failure the promise simply never resolves — poster stays.
				var first = document.getElementsByTagName('script')[0];
				if (first && first.parentNode) {
					first.parentNode.insertBefore(tag, first);
				} else {
					(document.head || document.body).appendChild(tag);
				}
			}
		});
		return ytApiPromise;
	}

	function extractYouTubeId(input) {
		if (!input || typeof input !== 'string') {
			return '';
		}
		var s = input.replace(/^\s+|\s+$/g, '');
		if (/^[A-Za-z0-9_-]{11}$/.test(s)) {
			return s;
		}
		var m;
		m = s.match(/youtu\.be\/([A-Za-z0-9_-]{11})/);
		if (m) {
			return m[1];
		}
		m = s.match(/[?&]v=([A-Za-z0-9_-]{11})/);
		if (m) {
			return m[1];
		}
		m = s.match(/\/embed\/([A-Za-z0-9_-]{11})/);
		if (m) {
			return m[1];
		}
		m = s.match(/\/shorts\/([A-Za-z0-9_-]{11})/);
		if (m) {
			return m[1];
		}
		return '';
	}

	// Build the chrome-suppressed YouTube player, wire lazy build / crop-sizing
	// / looping / off-screen pause, and return the control adapter. Returns null
	// for poster-only (unparseable id).
	function initYouTube(section, layer, cfg, reduce, ctx) {
		var id = extractYouTubeId(cfg.youtube);
		if (!id) {
			return null;
		}

		var startAt = (typeof cfg.start === 'number' && cfg.start > 0) ? cfg.start : 0;
		var endAt = (typeof cfg.end === 'number' && cfg.end > 0) ? cfg.end : 0;

		var frameEl = document.createElement('div');
		frameEl.className = 'vbg-layer__yt';
		var mount = document.createElement('div');
		frameEl.appendChild(mount);
		layer.appendChild(frameEl);

		var player = null;
		var built = false;
		var readyTimer = null;
		var loopTimer = null;

		function computeCover() {
			var w = section.offsetWidth;
			var h = section.offsetHeight;
			if (!w || !h) {
				return null;
			}
			var crop = (typeof cfg.crop === 'number') ? cfg.crop / 100 : 0.15;
			if (crop < 0 || crop > 0.4) {
				crop = 0.15;
			}
			// Height must cover the section PLUS the strip being hidden.
			var unit = Math.max(w / 16, (h / (1 - crop)) / 9) * 1.04;
			var fw = unit * 16;
			var fh = unit * 9;
			var top = -(fh * crop);
			if (top + fh < h) {
				top = h - fh;
			}
			return { w: w, h: h, fw: fw, fh: fh, left: (w - fw) / 2, top: top };
		}

		function sizeFrame() {
			var c = computeCover();
			if (!c) {
				return;
			}
			frameEl.style.width = c.fw + 'px';
			frameEl.style.height = c.fh + 'px';
			frameEl.style.left = c.left + 'px';
			frameEl.style.top = c.top + 'px';
			// Keep YouTube's own size model in sync with the displayed size, so it
			// serves quality for the real (large) player instead of the 640x360
			// default it would otherwise lock in.
			if (player && player.setSize) {
				try {
					player.setSize(Math.round(c.fw), Math.round(c.fh));
				} catch (e) {}
			}
		}

		sizeFrame();
		if (window.ResizeObserver) {
			var ro = new ResizeObserver(function () {
				sizeFrame();
			});
			ro.observe(section);
		} else {
			window.addEventListener('resize', sizeFrame);
		}

		function startReadyPoll() {
			if (readyTimer) {
				return;
			}
			var holdTime = (typeof cfg.hold === 'number' && cfg.hold > 0) ? cfg.hold : 0.35;
			var elapsed = 0;
			var step = 80;
			readyTimer = window.setInterval(function () {
				elapsed += step;
				var playing = false;
				var ct = 0;
				try {
					playing = window.YT && player.getPlayerState() === window.YT.PlayerState.PLAYING;
					ct = player.getCurrentTime();
				} catch (e) {}
				if ((playing && ct > (startAt + holdTime)) || elapsed >= 10000) {
					window.clearInterval(readyTimer);
					readyTimer = null;
					setReady(ctx);
				}
			}, step);
		}

		function disableCaptions() {
			// cc_load_policy:0 only prevents captions being auto-ON; a viewer whose
			// account/global setting forces subtitles will still see them. Unloading
			// the caption module is the reliable kill. The module name differs across
			// player builds, and it reloads when playback (re)starts, so we re-run it.
			try {
				player.unloadModule('captions');
			} catch (e) {}
			try {
				player.unloadModule('cc');
			} catch (e2) {}
		}

		function startLoopPoller() {
			if (!endAt || loopTimer) {
				return;
			}
			loopTimer = window.setInterval(function () {
				var ct;
				try {
					ct = player.getCurrentTime();
				} catch (e) {
					return;
				}
				if (ct >= endAt - 0.25) {
					try {
						player.seekTo(startAt, true);
					} catch (e2) {}
					disableCaptions();
				}
			}, 200);
		}

		function onReady() {
			sizeFrame();
			disableCaptions();
			// Best-effort quality hint. Modern YouTube ignores this and selects by
			// player size (handled above), but it is harmless on older clients.
			try {
				if (player.setPlaybackQuality) {
					player.setPlaybackQuality('hd1080');
				}
			} catch (eq) {}
			// Honor a stored sound preference (best effort; may be muted by policy).
			if (ctx.wantSound) {
				try {
					player.unMute();
					if (player.setVolume) {
						player.setVolume(100);
					}
				} catch (e) {}
			}
			if (reduce) {
				setReady(ctx);
				setPaused(ctx, true);
			} else {
				try {
					player.playVideo();
				} catch (e2) {}
				startReadyPoll();
			}
			if (endAt) {
				startLoopPoller();
			}
		}

		function onStateChange(e) {
			if (!window.YT || !e) {
				return;
			}
			// The caption module reloads when playback starts; kill it again.
			if (e.data === window.YT.PlayerState.PLAYING) {
				disableCaptions();
			}
			// Loop back for videos with no explicit end point.
			if (e.data === window.YT.PlayerState.ENDED) {
				try {
					player.seekTo(startAt, true);
					player.playVideo();
				} catch (err) {}
				disableCaptions();
			}
		}

		function buildPlayer() {
			if (built) {
				return;
			}
			built = true;
			loadYouTubeApi().then(function () {
				if (!window.YT || !window.YT.Player) {
					return;
				}
				var pv = {
					autoplay: reduce ? 0 : 1,
					mute: 1,
					controls: 0,
					disablekb: 1,
					fs: 0,
					rel: 0,
					iv_load_policy: 3,
					playsinline: 1,
					cc_load_policy: 0
				};
				if (startAt) {
					pv.start = startAt;
				}
				// Build at the real cover size: YouTube picks its initial quality
				// tier from the player's dimensions, so the 640x360 default would
				// stream a low tier that then gets upscaled across the section.
				var cover = computeCover();
				var iw = cover ? Math.round(cover.fw) : 1280;
				var ih = cover ? Math.round(cover.fh) : 720;
				try {
					player = new window.YT.Player(mount, {
						width: iw,
						height: ih,
						host: 'https://www.youtube-nocookie.com',
						videoId: id,
						playerVars: pv,
						events: {
							onReady: onReady,
							onStateChange: onStateChange
						}
					});
				} catch (err) {
					// Leave the poster showing.
				}
			})['catch'](function () {});
		}

		// Lazy build as the section approaches the viewport.
		if (!('IntersectionObserver' in window)) {
			buildPlayer();
		} else {
			var io = new IntersectionObserver(function (entries) {
				var i;
				for (i = 0; i < entries.length; i++) {
					if (entries[i].isIntersecting) {
						buildPlayer();
						io.disconnect();
						return;
					}
				}
			}, { rootMargin: '200px' });
			io.observe(section);

			// Pause off-screen, resume on scroll-back (never resume a deliberate pause).
			var vio = new IntersectionObserver(function (entries) {
				var j, en;
				for (j = 0; j < entries.length; j++) {
					en = entries[j];
					if (!player) {
						continue;
					}
					if (en.isIntersecting) {
						if (ctx.autoResume && !ctx.userPaused) {
							try {
								if (player.getPlayerState() !== window.YT.PlayerState.PLAYING) {
									player.playVideo();
								}
							} catch (e) {}
						}
					} else {
						try {
							if (player.getPlayerState() === window.YT.PlayerState.PLAYING) {
								player.pauseVideo();
							}
						} catch (e2) {}
					}
				}
			}, { threshold: 0.01 });
			vio.observe(section);
		}

		// Cleanup for editor live-rebuilds: stop pollers, observers, and player.
		ctx.teardowns.push(function () {
			if (readyTimer) {
				window.clearInterval(readyTimer);
				readyTimer = null;
			}
			if (loopTimer) {
				window.clearInterval(loopTimer);
				loopTimer = null;
			}
			if (ro) {
				try {
					ro.disconnect();
				} catch (e) {}
			} else {
				window.removeEventListener('resize', sizeFrame);
			}
			if (io) {
				try {
					io.disconnect();
				} catch (e2) {}
			}
			if (vio) {
				try {
					vio.disconnect();
				} catch (e3) {}
			}
			if (player && player.destroy) {
				try {
					player.destroy();
				} catch (e4) {}
			}
		});

		return {
			play: function () {
				if (player && player.playVideo) {
					try {
						player.playVideo();
					} catch (e) {}
				}
			},
			pause: function () {
				if (player && player.pauseVideo) {
					try {
						player.pauseVideo();
					} catch (e) {}
				}
			},
			mute: function () {
				if (player && player.mute) {
					try {
						player.mute();
					} catch (e) {}
				}
			},
			unmute: function () {
				if (player) {
					try {
						player.unMute();
						if (player.setVolume) {
							player.setVolume(100);
						}
					} catch (e) {}
				}
			},
			isPaused: function () {
				if (!player || !player.getPlayerState || !window.YT) {
					return true;
				}
				try {
					return player.getPlayerState() !== window.YT.PlayerState.PLAYING;
				} catch (e) {
					return true;
				}
			}
		};
	}

	// ---- Wistia source --------------------------------------------------

	// Mirror of metahotels_vbg_extract_wistia_id() in vbg-elementor.php. PHP is
	// the authority on the frontend, but the editor rebuilds the config from the
	// live model (see editorConfig), so the same reduction must exist here.
	function extractWistiaId(input) {
		if (!input || typeof input !== 'string') {
			return '';
		}
		var s = input.replace(/^\s+|\s+$/g, '');
		if (/^[a-z0-9]{10}$/.test(s)) {
			return s;
		}
		var m;
		m = s.match(/wistia\.(?:com|net)\/medias\/([A-Za-z0-9]{6,20})/);
		if (m) {
			return m[1];
		}
		m = s.match(/wistia\.(?:com|net)\/embed\/(?:iframe\/|medias\/)?([A-Za-z0-9]{6,20})/);
		if (m) {
			return m[1];
		}
		m = s.match(/media-id=["']([A-Za-z0-9]{6,20})["']/);
		if (m) {
			return m[1];
		}
		m = s.match(/wistia_async_([A-Za-z0-9]{6,20})/);
		if (m) {
			return m[1];
		}
		return '';
	}

	// Inject E-v1.js once per page. No promise is needed: the _wq queue is
	// designed to accept pushes before the script lands, and Wistia drains it on
	// load. On load failure nothing drains and the poster simply stays.
	function ensureWistiaScript() {
		window._wq = window._wq || [];
		if (document.getElementById('vbg-wistia-api')) {
			return;
		}
		var tag = document.createElement('script');
		tag.id = 'vbg-wistia-api';
		tag.src = 'https://fast.wistia.com/assets/external/E-v1.js';
		tag.async = true;
		var first = document.getElementsByTagName('script')[0];
		if (first && first.parentNode) {
			first.parentNode.insertBefore(tag, first);
		} else {
			(document.head || document.body).appendChild(tag);
		}
	}

	// Build the chrome-suppressed Wistia player, wire lazy build / looping /
	// off-screen pause, and return the control adapter. Returns null for
	// poster-only (unparseable id).
	//
	// Unlike YouTube there is no cover math here: fitStrategy 'cover' makes
	// Wistia fill the box itself, and the player renders no title bar, so the
	// crop control is not offered for this source.
	function initWistia(section, layer, cfg, reduce, ctx) {
		var id = extractWistiaId(cfg.wistia);
		if (!id) {
			return null;
		}

		var startAt = (typeof cfg.start === 'number' && cfg.start > 0) ? cfg.start : 0;
		var endAt = (typeof cfg.end === 'number' && cfg.end > 0) ? cfg.end : 0;

		var frameEl = document.createElement('div');
		frameEl.className = 'vbg-layer__wistia';
		layer.appendChild(frameEl);

		var handle = null;
		var built = false;
		var wqEntry = null;
		var revealTimer = null;
		var safetyTimer = null;

		// Hold the poster briefly so the player's first frame is never seen
		// snapping into place. Idempotent — whichever trigger fires first wins.
		function reveal() {
			if (revealTimer) {
				return;
			}
			var hold = (typeof cfg.hold === 'number' && cfg.hold > 0) ? cfg.hold : 0.35;
			revealTimer = window.setTimeout(function () {
				setReady(ctx);
			}, hold * 1000);
		}

		function onReady(video) {
			handle = video;

			if (startAt) {
				try {
					video.time(startAt);
				} catch (e) {}
			}
			// Honor a stored sound preference (best effort; may be refused).
			if (ctx.wantSound) {
				try {
					video.unmute();
				} catch (e2) {}
			}
			// Trim the loop to [start, end]. Without an end point the player's own
			// endVideoBehavior:'loop' already restarts it.
			if (endAt) {
				try {
					video.bind('timechange', function (t) {
						if (t >= endAt - 0.25) {
							try {
								video.time(startAt);
							} catch (e3) {}
						}
					});
				} catch (e4) {}
			}

			if (reduce) {
				setReady(ctx);
				setPaused(ctx, true);
				return;
			}

			try {
				video.bind('play', function () {
					reveal();
					return video.unbind;
				});
			} catch (e5) {}
			try {
				video.play();
			} catch (e6) {}

			// Reveal even if playback never starts (autoplay refused outright),
			// and correct the control glyph to match reality in that case.
			safetyTimer = window.setTimeout(function () {
				var playing = false;
				try {
					playing = (handle.state() === 'playing');
				} catch (e7) {}
				if (!playing) {
					setPaused(ctx, true);
				}
				reveal();
			}, 4000);
		}

		function buildPlayer() {
			if (built) {
				return;
			}
			built = true;
			ensureWistiaScript();

			var mount = document.createElement('div');
			mount.className = 'wistia_embed wistia_async_' + id;
			frameEl.appendChild(mount);

			wqEntry = {
				id: id,
				options: {
					autoPlay: !reduce,
					muted: true,
					silentAutoPlay: true,
					endVideoBehavior: 'loop',
					fitStrategy: 'cover',
					videoFoam: false,
					playbar: false,
					controlsVisibleOnLoad: false,
					playButton: false,
					smallPlayButton: false,
					fullscreenButton: false,
					settingsControl: false,
					volumeControl: false,
					playsinline: true,
					seo: false,
					// Suppresses the branding badge on load. Wistia still
					// re-shows it on pause, so vbg.css hides it outright too.
					wistiaLogo: false,
					doNotTrack: !cfg.track
				},
				onReady: onReady
			};
			window._wq.push(wqEntry);
		}

		// Lazy build as the section approaches the viewport.
		var io = null;
		var vio = null;
		if (!('IntersectionObserver' in window)) {
			buildPlayer();
		} else {
			io = new IntersectionObserver(function (entries) {
				var i;
				for (i = 0; i < entries.length; i++) {
					if (entries[i].isIntersecting) {
						buildPlayer();
						io.disconnect();
						return;
					}
				}
			}, { rootMargin: '200px' });
			io.observe(section);

			// Pause off-screen, resume on scroll-back (never resume a deliberate pause).
			vio = new IntersectionObserver(function (entries) {
				var j, en, state;
				for (j = 0; j < entries.length; j++) {
					en = entries[j];
					if (!handle) {
						continue;
					}
					try {
						state = handle.state();
					} catch (e) {
						continue;
					}
					if (en.isIntersecting) {
						if (ctx.autoResume && !ctx.userPaused && state !== 'playing') {
							try {
								handle.play();
							} catch (e2) {}
						}
					} else if (state === 'playing') {
						try {
							handle.pause();
						} catch (e3) {}
					}
				}
			}, { threshold: 0.01 });
			vio.observe(section);
		}

		// Cleanup for editor live-rebuilds: stop timers, observers, and player.
		ctx.teardowns.push(function () {
			if (revealTimer) {
				window.clearTimeout(revealTimer);
				revealTimer = null;
			}
			if (safetyTimer) {
				window.clearTimeout(safetyTimer);
				safetyTimer = null;
			}
			if (io) {
				try {
					io.disconnect();
				} catch (e) {}
			}
			if (vio) {
				try {
					vio.disconnect();
				} catch (e2) {}
			}
			// Drop the queued config so a rebuild does not re-init this player.
			if (wqEntry && window._wq) {
				try {
					window._wq.push({ revoke: wqEntry });
				} catch (e3) {}
			}
			if (handle && handle.remove) {
				try {
					handle.remove();
				} catch (e4) {}
			}
		});

		return {
			play: function () {
				if (handle && handle.play) {
					try {
						handle.play();
					} catch (e) {}
				}
			},
			pause: function () {
				if (handle && handle.pause) {
					try {
						handle.pause();
					} catch (e) {}
				}
			},
			mute: function () {
				if (handle && handle.mute) {
					try {
						handle.mute();
					} catch (e) {}
				}
			},
			unmute: function () {
				if (handle) {
					try {
						handle.unmute();
						if (handle.volume) {
							handle.volume(1);
						}
					} catch (e) {}
				}
			},
			isPaused: function () {
				if (!handle || !handle.state) {
					return true;
				}
				try {
					return handle.state() !== 'playing';
				} catch (e) {
					return true;
				}
			}
		};
	}

	// ---- Layer assembly (shared) ----------------------------------------

	function buildLayer(section) {
		if (!section || section.getAttribute('data-vbg-ready') === '1') {
			return;
		}
		var raw = section.getAttribute('data-vbg');
		if (!raw) {
			return;
		}
		var cfg;
		try {
			cfg = JSON.parse(raw);
		} catch (e) {
			return;
		}
		if (!cfg || typeof cfg !== 'object') {
			return;
		}

		var reduce = prefersReducedMotion();
		var mobile = isMobileViewport();
		var source = (cfg.source === 'youtube' || cfg.source === 'wistia') ? cfg.source : 'file';

		section.setAttribute('data-vbg-ready', '1');

		var layer = document.createElement('div');
		layer.className = 'vbg-layer';

		// Poster first so it sits BELOW the media (video / YouTube frame).
		if (cfg.poster) {
			var poster = document.createElement('div');
			poster.className = 'vbg-layer__poster';
			poster.style.backgroundImage = 'url("' + String(cfg.poster).replace(/"/g, '%22') + '")';
			layer.appendChild(poster);
		}

		// Insert the layer as the section's first child before wiring media, so
		// observers and player construction run against an in-DOM element.
		if (section.firstChild) {
			section.insertBefore(layer, section.firstChild);
		} else {
			section.appendChild(layer);
		}

		var ctx = {
			section: section,
			layer: layer,
			cfg: cfg,
			i18n: cfg.i18n || { play: 'Play video', pause: 'Pause video', on: 'ON', off: 'OFF' },
			reduce: reduce,
			wantSound: getStoredSound(),
			userPaused: reduce ? true : false,
			autoResume: reduce ? false : true,
			video: null,
			src: '',
			srcAssigned: false,
			adapter: null,
			playBtn: null,
			switchEl: null,
			switchTextEl: null,
			teardowns: []
		};

		var adapter;
		if (source === 'youtube') {
			adapter = initYouTube(section, layer, cfg, reduce, ctx);
		} else if (source === 'wistia') {
			// Respect the poster-only choice on small screens: skip the player
			// entirely so no third-party script is fetched over mobile data.
			if (mobile && cfg.mobileBehavior === 'poster') {
				adapter = null;
			} else {
				adapter = initWistia(section, layer, cfg, reduce, ctx);
			}
		} else {
			adapter = initFile(section, layer, cfg, reduce, mobile, ctx);
		}
		ctx.adapter = adapter;

		// Expose teardown so an editor rebuild can stop this instance's timers
		// and observers before the layer is replaced.
		section.__vbgTeardown = function () {
			var i;
			for (i = 0; i < ctx.teardowns.length; i++) {
				try {
					ctx.teardowns[i]();
				} catch (e) {}
			}
			ctx.teardowns = [];
		};

		// Scrim above the media, below the controls.
		var scrim = document.createElement('div');
		scrim.className = 'vbg-layer__scrim';
		layer.appendChild(scrim);

		// Controls last so they stay on top; only when there is a playable media.
		if (adapter && cfg.showControls) {
			layer.appendChild(buildControls(ctx));
		}

		// Poster-only (no adapter): nothing to play, just reveal.
		if (!adapter) {
			setReady(ctx);
		}
	}

	function scan(root) {
		var scope = root && root.querySelectorAll ? root : document;
		var nodes = scope.querySelectorAll('.has-vbg[data-vbg]');
		var i;
		for (i = 0; i < nodes.length; i++) {
			buildLayer(nodes[i]);
		}
	}

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	// ---- Editor live-rebuild hooks --------------------------------------

	function removeClass(el, name) {
		el.className = (' ' + el.className + ' ').split(' ' + name + ' ').join(' ').replace(/^\s+|\s+$/g, '');
	}

	// Locate the Elementor editor container for a rendered element, so its live
	// settings can be read. Returns null on the frontend or if unavailable.
	function editorGetContainer(el) {
		try {
			var id = el.getAttribute('data-id');
			if (!id) {
				return null;
			}
			var ed = window.elementor || (window.parent && window.parent.elementor);
			if (!ed || !ed.getContainer) {
				return null;
			}
			return ed.getContainer(id) || null;
		} catch (e) {
			return null;
		}
	}

	// Rebuild the same config object PHP emits, from the element's live editor
	// settings — so panel changes reflect without a save/reload. Returns null
	// when the feature is off or there is nothing to render.
	function editorConfig(container) {
		function g(k) {
			try {
				return container.settings.get(k);
			} catch (e) {
				return undefined;
			}
		}
		if (g('vbg_enable') !== 'yes') {
			return null;
		}

		var rawSource = g('vbg_source');
		var source = (rawSource === 'youtube' || rawSource === 'wistia') ? rawSource : 'file';
		var posterObj = g('vbg_poster');
		var poster = (posterObj && posterObj.url) ? posterObj.url : '';

		var cfg = {};
		if (source === 'wistia') {
			var wistia = extractWistiaId('' + (g('vbg_wistia') || ''));
			// Same swatch fallback the PHP builder applies.
			if (!poster && wistia) {
				poster = 'https://fast.wistia.com/embed/medias/' + wistia + '/swatch';
			}
			if (!wistia && !poster) {
				return null;
			}
			cfg.source = 'wistia';
			cfg.wistia = wistia;
			cfg.start = parseInt(g('vbg_start'), 10) || 0;
			cfg.end = parseInt(g('vbg_end'), 10) || 0;
			cfg.hold = parseFloat(g('vbg_hold')) || 0;
			cfg.track = (g('vbg_wistia_track') === 'yes');
		} else if (source === 'youtube') {
			var youtube = ('' + (g('vbg_youtube') || '')).replace(/^\s+|\s+$/g, '');
			if (!youtube && !poster) {
				return null;
			}
			var cropObj = g('vbg_crop');
			cfg.source = 'youtube';
			cfg.youtube = youtube;
			cfg.start = parseInt(g('vbg_start'), 10) || 0;
			cfg.end = parseInt(g('vbg_end'), 10) || 0;
			cfg.crop = (cropObj && typeof cropObj.size !== 'undefined' && cropObj.size !== '') ? parseFloat(cropObj.size) : 15;
			cfg.hold = parseFloat(g('vbg_hold')) || 0;
		} else {
			var desktop = ('' + (g('vbg_desktop') || '')).replace(/^\s+|\s+$/g, '');
			var mobile = ('' + (g('vbg_mobile') || '')).replace(/^\s+|\s+$/g, '');
			if (!desktop && !mobile && !poster) {
				return null;
			}
			cfg.desktop = desktop;
			cfg.mobile = mobile;
		}

		cfg.poster = poster;
		cfg.mobileBehavior = (g('vbg_mobile_behavior') === 'poster') ? 'poster' : 'video';
		cfg.showControls = (g('vbg_show_controls') === 'yes');
		var label = ('' + (g('vbg_label') || '')).replace(/<[^>]*>/g, '');
		cfg.label = label || 'Volume';
		var pos = g('vbg_controls_position');
		cfg.position = (pos === 'bl' || pos === 'tr' || pos === 'tl' || pos === 'br') ? pos : 'br';
		cfg.i18n = { play: 'Play video', pause: 'Pause video', on: 'ON', off: 'OFF' };
		return cfg;
	}

	function rebuildScope($scope) {
		var el = $scope && $scope[0] ? $scope[0] : $scope;
		if (!el || !el.getAttribute) {
			return;
		}
		if (typeof el.__vbgTeardown === 'function') {
			try {
				el.__vbgTeardown();
			} catch (e) {}
			el.__vbgTeardown = null;
		}
		removeChildLayer(el);
		el.removeAttribute('data-vbg-ready');

		// In the editor, refresh the config from the live model. PHP before_render
		// doesn't run on client-side re-renders, so the data-vbg attribute may be
		// stale or gone. If the model isn't reachable, fall back to data-vbg.
		var container = editorGetContainer(el);
		if (container) {
			var cfg = editorConfig(container);
			if (cfg) {
				el.setAttribute('data-vbg', JSON.stringify(cfg));
				if (!hasClass(el, 'has-vbg')) {
					el.className += ' has-vbg';
				}
			} else {
				el.removeAttribute('data-vbg');
				removeClass(el, 'has-vbg');
				return;
			}
		}
		buildLayer(el);
	}

	function initEditorHooks() {
		if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
			return;
		}
		if (!(elementorFrontend.isEditMode && elementorFrontend.isEditMode())) {
			return;
		}
		elementorFrontend.hooks.addAction('frontend/element_ready/section', rebuildScope);
		elementorFrontend.hooks.addAction('frontend/element_ready/container', rebuildScope);
	}

	if (window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', initEditorHooks);
	}

	// Frontend build.
	ready(function () {
		scan(document);
	});
})();
