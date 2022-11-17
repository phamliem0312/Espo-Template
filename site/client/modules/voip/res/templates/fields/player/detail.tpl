{{#if isRecording}}

<link rel="stylesheet" href="client/modules/voip/lib/soundmanager/css/bar-ui.css" />
<style type="text/css">
.sm2-bar-ui .sm2-playlist {
    display: none;
}
.sm2-bar-ui {
    font-size: 14px;
}
.sm2-bar-ui .sm2-main-controls,
.sm2-bar-ui .sm2-playlist-drawer {
    background-color: #DFE4E4;
}
</style>

<div class="sm2-bar-ui compact full-width dark-text flat">

 <div class="bd sm2-main-controls">

  <div class="sm2-inline-texture"></div>
  <div class="sm2-inline-gradient"></div>

  <div class="sm2-inline-element sm2-button-element">
   <div class="sm2-button-bd">
    <a href="#play" class="sm2-inline-button sm2-icon-play-pause">Play / pause</a>
   </div>
  </div>

  <div class="sm2-inline-element sm2-inline-status">

   <div class="sm2-playlist">
    <div class="sm2-playlist-target">
     <noscript><p>JavaScript is required.</p></noscript>
    </div>
   </div>

   <div class="sm2-progress">
    <div class="sm2-row">
    <div class="sm2-inline-time">0:00</div>
     <div class="sm2-progress-bd">
      <div class="sm2-progress-track">
       <div class="sm2-progress-bar"></div>
       <div class="sm2-progress-ball"><div class="icon-overlay"></div></div>
      </div>
     </div>
     <div class="sm2-inline-duration">0:00</div>
    </div>
   </div>

  </div>

  <div class="sm2-inline-element sm2-button-element sm2-volume">
   <div class="sm2-button-bd">
    <span class="sm2-inline-button sm2-volume-control volume-shade"></span>
    <a href="#volume" class="sm2-inline-button sm2-volume-control">volume</a>
   </div>
  </div>

 </div>

 <div class="bd sm2-playlist-drawer sm2-element">

  <div class="sm2-inline-texture">
   <div class="sm2-box-shadow"></div>
  </div>

  <div class="sm2-playlist-wrapper">
    <ul class="sm2-playlist-bd">
      <li><a href="{{value}}">&nbsp;</a></li>
    </ul>
  </div>

 </div>

</div>
{{else}}
  {{translate 'noRecording' scope='Call' category='labels'}}
{{/if}}
