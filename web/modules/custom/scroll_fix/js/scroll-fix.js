(function ($, Drupal) {
  Drupal.behaviors.preventScrollToBottom = {
    attach: function (context) {
      if (context !== document) return;
      console.log('✅ Scroll Fix behavior attached (module)');

      if (!(window.CKEDITOR && CKEDITOR.instances)) {
        console.log('ℹ️ CKEditor not detected');
        return;
      }

      Object.keys(CKEDITOR.instances).forEach(function (id) {
        var inst = CKEDITOR.instances[id];
        if (inst._scrollFixWired) return; // avoid double wiring
        inst._scrollFixWired = true;

        // Flags
        inst._ready = false;           // becomes true after instanceReady
        inst._armedForScroll = false;  // set true only when media insert is triggered

        function scrollToEditor() {
          var iframe =
            inst.container && inst.container.$
              ? inst.container.$.querySelector('iframe.cke_wysiwyg_frame')
              : null;
          var target = iframe || (inst.container && inst.container.$) || null;
          if (!target) return;

          // Let Drupal/CKE finish any default scrolling, then correct it.
          requestAnimationFrame(function () {
            setTimeout(function () {
              try {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
              } catch (e) {
                var rect = target.getBoundingClientRect();
                window.scrollTo({
                  top: window.scrollY + rect.top - window.innerHeight / 2,
                  behavior: 'smooth'
                });
              }
            }, 50);
          });
        }

        // 1) Only mark editor ready after it’s fully initialized.
        inst.on('instanceReady', function () {
          inst._ready = true;
          // Do NOT scroll here.
          // console.log('🟢 instanceReady', id);
        });

        // 2) When media-related toolbar commands run, arm the scroll.
        //    Drupal/CKE command names vary; cover common ones.
        inst.on('afterCommandExec', function (evt) {
          if (!inst._ready) return;
          var name = (evt && evt.data && evt.data.name) ? evt.data.name : '';
          if (/(media|image|drupal)/i.test(name)) {
            // Arm for the *next* content change/insert that follows the command.
            inst._armedForScroll = true;
            // console.log('🎯 Armed by command:', name);
          }
        });

        // 3) afterInsertHtml fires when content is injected (e.g., media insert).
        inst.on('afterInsertHtml', function () {
          if (!inst._ready) return;
          // This is the most precise signal; scroll immediately and disarm.
          inst._armedForScroll = false;
          // console.log('🧩 afterInsertHtml → scroll');
          scrollToEditor();
        });

        // 4) change can also follow an insert; only scroll if we were armed by a media command.
        inst.on('change', function () {
          if (!inst._ready) return;
          if (inst._armedForScroll) {
            inst._armedForScroll = false;
            // console.log('✏️ change (armed) → scroll');
            scrollToEditor();
          }
        });

        // Optional: when the editor regains focus after closing the modal, only scroll if armed.
        inst.on('focus', function () {
          if (!inst._ready) return;
          if (inst._armedForScroll) {
            inst._armedForScroll = false;
            // console.log('👀 focus (armed) → scroll');
            scrollToEditor();
          }
        });

        console.log('🔌 Wired CKEditor4 scroll-fix for', id);
      });
    }
  };
})(jQuery, Drupal);