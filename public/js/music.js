(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var widget = document.getElementById('musicWidget');
    if (!widget) return;
    var toggle = document.getElementById('musicToggle');
    var frame = document.querySelector('.music-frame');
    var audio = document.getElementById('musicPlayer');
    var tip = document.getElementById('musicTip');
    var playing = false;

    function isYoutube() { return !!frame && window.location.search.indexOf('youtube') > -1 || false; }

    if (audio) {
      var vol = parseInt(window.__NH_VOLUME || '40', 10);
      audio.volume = vol / 100;
    }

    toggle.addEventListener('click', function () {
      if (frame && frame.src) {
        if (!playing) {
          frame.style.display = 'block';
          frame.src = frame.src + (frame.src.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1';
        } else {
          frame.style.display = 'none';
        }
        playing = !playing;
        toggle.classList.toggle('playing', playing);
        tip.textContent = playing ? 'Pause music' : 'Play music';
        return;
      }
      if (audio) {
        if (audio.paused) {
          audio.play().then(function () {
            playing = true;
            toggle.classList.add('playing');
            tip.textContent = 'Pause music';
          }).catch(function () {
            tip.textContent = 'Tap again to play';
          });
        } else {
          audio.pause();
          playing = false;
          toggle.classList.remove('playing');
          tip.textContent = 'Play music';
        }
      }
    });
  });
})();
