<template>
  <!-- Homepage: absolute bg-white left-[132px] top-[249px] w-[1920px] h-[1080px] -->
  <div class="absolute bg-white h-[1080px] left-[132px] overflow-clip top-[249px] w-[1920px]">

    <!-- ═══════════════════════════════════════════════════════════════
         Homepage1 inner content (left-[76px] w-[1844px])
         ═══════════════════════════════════════════════════════════════ -->
    <div class="absolute bg-white h-[1080px] left-[76px] overflow-clip top-0 w-[1844px]">

      <!-- Group: ambient blur circle -->
      <div class="-translate-x-1/2 absolute left-[calc(45.83%+33.29px)] size-[786.904px] top-[310.04px]">
        <div class="absolute inset-[-63.54%]">
          <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 1786.9 1786.9">
            <g id="Group 41">
              <g filter="url(#filter0_f_6_1223)" id="Ellipse 10">
                <circle cx="893.452" cy="893.452" fill="var(--fill-0, #528950)" fill-opacity="0.02" r="393.452" />
              </g>
            </g>
            <defs>
              <filter color-interpolation-filters="sRGB" filter-units="userSpaceOnUse" height="1786.9" id="filter0_f_6_1223" width="1786.9" x="0" y="-7.62939e-06">
                <feFlood flood-opacity="0" result="BackgroundImageFix" />
                <feBlend in="SourceGraphic" in2="BackgroundImageFix" mode="normal" result="shape" />
                <feGaussianBlur result="effect1_foregroundBlur_6_1223" std-deviation="250" />
              </filter>
            </defs>
          </svg>
        </div>
      </div>

      <!-- Search pill (white rounded bar) -->
      <div class="-translate-x-1/2 absolute bg-white h-[78px] left-[calc(50%+0.5px)] rounded-[63px] shadow-[0px_4px_24.2px_2px_rgba(0,0,0,0.21)] top-[934px] w-[967px]" />
      <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[18px] justify-center leading-[0] left-[calc(25%+51px)] not-italic text-[15px] text-black top-[974px] w-[410px]">
        <p class="leading-[normal]">Estuda com a ajuda do Tut's</p>
      </div>

      <!-- Send button (Group25 → Group6 → Group30 → Group2 circle + Send icon) -->
      <div class="absolute left-[calc(66.67%+87.67px)] size-[56px] top-[945px]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 56.0002 56">
          <circle cx="28.0002" cy="28" fill="var(--fill-0, #A7C68E)" id="Ellipse 656" r="28" />
        </svg>
      </div>
      <div class="absolute h-[20.837px] left-[calc(66.67%+103.29px)] top-[963.23px] w-[24.744px]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 24.7442 20.8372">
          <g :clip-path="'url(#clip0_6_1186)'" id="send">
            <path :d="svgPaths.p19c2f80" fill="var(--fill-0, white)" id="Vector" />
          </g>
          <defs>
            <clipPath id="clip0_6_1186">
              <rect fill="white" height="20.8372" width="24.7442" />
            </clipPath>
          </defs>
        </svg>
      </div>

      <!-- Mic/attach icon (Group31 → Group7 → Group32) -->
      <div class="absolute h-[69px] left-[calc(25%+2px)] top-[915px] w-[22px]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 22 69">
          <g id="Group 432">
            <path :d="svgPaths.p2a2c7c80" fill="var(--fill-0, black)" id="Vector" />
          </g>
        </svg>
      </div>

      <!-- ─── UC Card 1 (Group61) ──────────────────────────────────── -->
      <!-- Masked photo (MaskGroup) -->
      <div
        class="absolute h-[186.449px] left-[calc(25%+23px)] mask-alpha mask-intersect mask-no-clip mask-no-repeat mask-position-[0px_-24.81px] mask-size-[271px_211.259px] rounded-[30px] top-[239.38px] w-[271px]"
        :style="{ maskImage: `url('${imgSusanQYin2JIvboGLehoUnsplash1}')` }"
      >
        <div class="absolute inset-0 opacity-61 overflow-hidden pointer-events-none rounded-[30px]">
          <img alt="" class="absolute h-[146.09%] left-0 max-w-none top-[-24.26%] w-[173.48%]" :src="imgSusanQYin2JIvboGLehoUnsplash2" />
        </div>
      </div>
      <!-- Card shadow SVG (Group51) -->
      <div class="absolute h-[143.725px] left-[calc(25%+23px)] top-[284.7px] w-[271px]">
        <div class="absolute inset-[0_-1.48%_-5.57%_-1.48%]">
          <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 279 151.725">
            <g filter="url(#filter0_d_6_1184)">
              <path :d="svgPaths.pd82f280" fill="var(--fill-0, white)" />
            </g>
            <defs>
              <filter color-interpolation-filters="sRGB" filter-units="userSpaceOnUse" height="151.725" id="filter0_d_6_1184" width="279" x="8.10562e-09" y="0">
                <feFlood flood-opacity="0" result="BackgroundImageFix" />
                <feColorMatrix in="SourceAlpha" result="hardAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" />
                <feOffset dy="4" />
                <feGaussianBlur std-deviation="2" />
                <feComposite in2="hardAlpha" operator="out" />
                <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.25 0" />
                <feBlend in2="BackgroundImageFix" mode="normal" result="effect1_dropShadow_6_1184" />
                <feBlend in="SourceGraphic" in2="effect1_dropShadow_6_1184" mode="normal" result="shape" />
              </filter>
            </defs>
          </svg>
        </div>
      </div>
      <!-- Card 1 text -->
      <p class="absolute font-['Inter:Regular',sans-serif] font-normal leading-[normal] left-[calc(25%+38px)] not-italic text-[#b7b9b8] text-[7px] top-[315px] whitespace-nowrap">2025/2026</p>
      <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(25%+38px)] not-italic text-[#1e1e1e] text-[20px] top-[335px] w-[182.54px]">
        <p class="leading-[normal]">Nome da UC</p>
      </div>
      <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(25%+38px)] not-italic text-[#656966] text-[7px] top-[356px] w-[77px]">
        <p class="leading-[normal]">Docente: Nome Nome</p>
      </div>
      <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(25%+38px)] not-italic text-[#656966] text-[7px] top-[364px] w-[77px]">
        <p class="leading-[normal]">Ano: Ano da Cadeira</p>
      </div>
      <!-- 3-dot button card 1 (Group61 → Group50) -->
      <div class="absolute left-[calc(33.33%+102.33px)] size-[31px] top-[270px]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 31 31">
          <circle cx="15.5" cy="15.5" fill="var(--fill-0, white)" id="Ellipse 916" r="15.5" />
        </svg>
      </div>
      <div class="absolute flex h-[11px] items-center justify-center left-[calc(33.33%+116.44px)] top-[280px] w-[2.893px]">
        <div class="flex-none rotate-180">
          <div class="h-[11px] relative w-[2.893px]">
            <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 2.89261 10.9998">
              <circle cx="1.44558" cy="1.44558" fill="var(--fill-0, #1E1E1E)" r="1.44558" />
              <circle cx="1.44699" cy="5.50687" fill="var(--fill-0, #1E1E1E)" r="1.44558" />
              <circle cx="1.44699" cy="9.55421" fill="var(--fill-0, #1E1E1E)" r="1.44558" />
            </svg>
          </div>
        </div>
      </div>

      <!-- ─── UC Card 2 (Group62) ──────────────────────────────────── -->
      <div
        class="absolute h-[186.449px] left-[calc(41.67%+18.67px)] mask-alpha mask-intersect mask-no-clip mask-no-repeat mask-position-[0px_-24.81px] mask-size-[271px_211.259px] rounded-[30px] top-[239.38px] w-[271px]"
        :style="{ maskImage: `url('${imgSusanQYin2JIvboGLehoUnsplash1}')` }"
      >
        <div class="absolute inset-0 opacity-61 overflow-hidden pointer-events-none rounded-[30px]">
          <img alt="" class="absolute h-[146.09%] left-[-69.18%] max-w-none top-[-28.02%] w-[173.48%]" :src="imgSusanQYin2JIvboGLehoUnsplash3" />
        </div>
      </div>
      <div class="absolute h-[143.725px] left-[calc(41.67%+18.67px)] top-[284.7px] w-[271px]">
        <div class="absolute inset-[0_-1.48%_-5.57%_-1.48%]">
          <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 279 151.725">
            <g filter="url(#filter0_d_6_1184b)">
              <path :d="svgPaths.pd82f280" fill="var(--fill-0, white)" />
            </g>
            <defs>
              <filter color-interpolation-filters="sRGB" filter-units="userSpaceOnUse" height="151.725" id="filter0_d_6_1184b" width="279" x="8.10562e-09" y="0">
                <feFlood flood-opacity="0" result="BackgroundImageFix" />
                <feColorMatrix in="SourceAlpha" result="hardAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" />
                <feOffset dy="4" />
                <feGaussianBlur std-deviation="2" />
                <feComposite in2="hardAlpha" operator="out" />
                <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.25 0" />
                <feBlend in2="BackgroundImageFix" mode="normal" result="effect1_dropShadow_6_1184" />
                <feBlend in="SourceGraphic" in2="effect1_dropShadow_6_1184" mode="normal" result="shape" />
              </filter>
            </defs>
          </svg>
        </div>
      </div>
      <p class="absolute font-['Inter:Regular',sans-serif] font-normal leading-[normal] left-[calc(41.67%+33.67px)] not-italic text-[#b7b9b8] text-[7px] top-[315px] whitespace-nowrap">2025/2026</p>
      <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(41.67%+33.67px)] not-italic text-[#1e1e1e] text-[20px] top-[335px] w-[182.54px]">
        <p class="leading-[normal]">Nome da UC</p>
      </div>
      <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(41.67%+33.67px)] not-italic text-[#656966] text-[7px] top-[356px] w-[77px]">
        <p class="leading-[normal]">Docente: Nome Nome</p>
      </div>
      <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(41.67%+33.67px)] not-italic text-[#656966] text-[7px] top-[364px] w-[77px]">
        <p class="leading-[normal]">Ano: Ano da Cadeira</p>
      </div>
      <!-- 3-dot button card 2 (Group62 → Group53) -->
      <div class="absolute left-[calc(50%+96px)] size-[31px] top-[270px]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 31 31">
          <circle cx="15.5" cy="15.5" fill="var(--fill-0, white)" id="Ellipse 916b" r="15.5" />
        </svg>
      </div>
      <div class="absolute flex h-[11px] items-center justify-center left-[calc(50%+110.11px)] top-[280px] w-[2.893px]">
        <div class="flex-none rotate-180">
          <div class="h-[11px] relative w-[2.893px]">
            <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 2.89257 10.9998">
              <circle cx="1.44558" cy="1.44558" fill="var(--fill-0, #1E1E1E)" r="1.44558" />
              <circle cx="1.44699" cy="5.50687" fill="var(--fill-0, #1E1E1E)" r="1.44558" />
              <circle cx="1.44699" cy="9.55421" fill="var(--fill-0, #1E1E1E)" r="1.44558" />
            </svg>
          </div>
        </div>
      </div>

      <!-- ─── UC Card 3 (Group63) ──────────────────────────────────── -->
      <!-- Masked photo (MaskGroup2) -->
      <div
        class="absolute h-[186.449px] left-[calc(58.33%+14.33px)] mask-alpha mask-intersect mask-no-clip mask-no-repeat mask-position-[0px_-24.81px] mask-size-[271px_211.259px] rounded-[30px] top-[239.38px] w-[271px]"
        :style="{ maskImage: `url('${imgSusanQYin2JIvboGLehoUnsplash1}')` }"
      >
        <div class="absolute inset-0 opacity-61 overflow-hidden pointer-events-none rounded-[30px]">
          <img alt="" class="absolute h-[146.09%] left-0 max-w-none top-[-10.28%] w-[173.48%]" :src="imgSusanQYin2JIvboGLehoUnsplash4" />
        </div>
      </div>
      <!-- Masked circle button (MaskGroup2 inner) -->
      <div
        class="absolute left-[calc(66.67%+93.67px)] mask-alpha mask-intersect mask-no-clip mask-no-repeat mask-position-[-233px_-55.428px] mask-size-[271px_211.259px] size-[31px] top-[270px]"
        :style="{ maskImage: `url('${imgSusanQYin2JIvboGLehoUnsplash1}')` }"
      >
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 31 31">
          <circle cx="15.5" cy="15.5" fill="var(--fill-0, white)" id="Ellipse 916c" r="15.5" />
        </svg>
      </div>
      <!-- 3-dot button card 3 (Group55 with mask) -->
      <div class="absolute flex h-[11px] items-center justify-center left-[calc(66.67%+107.77px)] top-[280px] w-[2.893px]">
        <div class="flex-none rotate-180">
          <div
            class="h-[11px] mask-alpha mask-intersect mask-no-clip mask-no-repeat mask-position-[-247.107px_-65.428px] mask-size-[271px_211.259px] relative w-[2.893px]"
            :style="{ maskImage: `url('${imgSusanQYin2JIvboGLehoUnsplash1}')` }"
          >
            <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 2.89257 10.9998">
              <circle cx="1.44558" cy="1.44558" fill="var(--fill-0, #1E1E1E)" r="1.44558" />
              <circle cx="1.44699" cy="5.50687" fill="var(--fill-0, #1E1E1E)" r="1.44558" />
              <circle cx="1.44699" cy="9.55421" fill="var(--fill-0, #1E1E1E)" r="1.44558" />
            </svg>
          </div>
        </div>
      </div>
      <!-- Card 3 shadow SVG (Group54) -->
      <div class="absolute h-[143.725px] left-[calc(58.33%+14.33px)] top-[284.7px] w-[271px]">
        <div class="absolute inset-[0_-1.48%_-5.57%_-1.48%]">
          <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 279 151.725">
            <g filter="url(#filter0_d_6_1184c)">
              <path :d="svgPaths.pd82f280" fill="var(--fill-0, white)" />
            </g>
            <defs>
              <filter color-interpolation-filters="sRGB" filter-units="userSpaceOnUse" height="151.725" id="filter0_d_6_1184c" width="279" x="8.10562e-09" y="0">
                <feFlood flood-opacity="0" result="BackgroundImageFix" />
                <feColorMatrix in="SourceAlpha" result="hardAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" />
                <feOffset dy="4" />
                <feGaussianBlur std-deviation="2" />
                <feComposite in2="hardAlpha" operator="out" />
                <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.25 0" />
                <feBlend in2="BackgroundImageFix" mode="normal" result="effect1_dropShadow_6_1184" />
                <feBlend in="SourceGraphic" in2="effect1_dropShadow_6_1184" mode="normal" result="shape" />
              </filter>
            </defs>
          </svg>
        </div>
      </div>
      <p class="absolute font-['Inter:Regular',sans-serif] font-normal leading-[normal] left-[calc(58.33%+29.33px)] not-italic text-[#b7b9b8] text-[7px] top-[315px] whitespace-nowrap">2025/2026</p>
      <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(58.33%+29.33px)] not-italic text-[#1e1e1e] text-[20px] top-[335px] w-[182.54px]">
        <p class="leading-[normal]">Nome da UC</p>
      </div>
      <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(58.33%+29.33px)] not-italic text-[#656966] text-[7px] top-[356px] w-[77px]">
        <p class="leading-[normal]">Docente: Nome Nome</p>
      </div>
      <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(58.33%+29.33px)] not-italic text-[#656966] text-[7px] top-[364px] w-[77px]">
        <p class="leading-[normal]">Ano: Ano da Cadeira</p>
      </div>

      <!-- ─── Footer (Group56) ─────────────────────────────────────── -->
      <!-- Send3 icon -->
      <div class="absolute left-[calc(58.33%+55.33px)] size-[7px] top-[1034px]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 7 7">
          <g :clip-path="'url(#clip0_6_1153)'" id="send-3">
            <path :d="svgPaths.p176b0a00" fill="var(--fill-0, #BBBBBB)" id="Vector" />
            <path :d="svgPaths.p9f4ae70" fill="var(--fill-0, #BBBBBB)" id="Vector_2" />
          </g>
          <defs>
            <clipPath id="clip0_6_1153">
              <rect fill="white" height="7" width="7" />
            </clipPath>
          </defs>
        </svg>
      </div>
      <!-- Footer text -->
      <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[18px] justify-center leading-[0] left-[calc(50%+8px)] not-italic text-[#b7b7b7] text-[9px] text-center top-[1037px] w-[406px]">
        <p>
          <span class="leading-[normal]">Criado por alunos e docentes que valorizam a qualidade, e com princípios de </span>
          <span class="decoration-solid font-['Inter:Bold',sans-serif] font-bold leading-[normal] not-italic underline">Responsible AI</span>
        </p>
      </div>
      <!-- DECA logo -->
      <div class="-translate-x-1/2 absolute h-[54px] left-[calc(37.5%-13px)] top-[1001px] w-[97px]">
        <div class="absolute inset-0 opacity-79 overflow-hidden pointer-events-none">
          <img alt="" class="absolute h-full left-[-13.07%] max-w-none top-0 w-[368.41%]" :src="imgDecaPtHorizontalExtensaPreto1" />
        </div>
      </div>

      <!-- Section headings -->
      <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(25%+96.5px)] not-italic text-[24px] text-black text-center top-[188.5px] whitespace-nowrap">
        <p class="leading-[normal]">As tuas UC's </p>
      </div>
      <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(25%+119.5px)] not-italic text-[24px] text-black text-center top-[467.5px] whitespace-nowrap">
        <p class="leading-[normal]">O teu calendário</p>
      </div>

      <!-- Group80: green arrow circle -->
      <div class="absolute left-[calc(66.67%+120.67px)] size-[40px] top-[311px]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 40 40">
          <g id="Group 620">
            <circle cx="20" cy="20" fill="var(--fill-0, #A7C68E)" id="Ellipse 656hp" r="20" />
            <path :d="svgPaths.p313e9b80" fill="var(--fill-0, white)" id="arrow-single-right" />
          </g>
        </svg>
      </div>

      <!-- Calendar screenshot image -->
      <div class="absolute h-[334px] left-[calc(25%+23px)] rounded-[24px] shadow-[0px_4px_11.3px_1px_rgba(0,0,0,0.25)] top-[505px] w-[877px]">
        <div class="absolute inset-0 overflow-hidden pointer-events-none rounded-[24px]">
          <img alt="" class="absolute h-[132.62%] left-0 max-w-none top-[-0.01%] w-[102%]" :src="imgCapturaDeEcra20260421As1946111" />
        </div>
      </div>
    </div><!-- /Homepage1 -->

    <!-- ═══════════════════════════════════════════════════════════════
         Sidebar (left-0 w-[76px])
         ═══════════════════════════════════════════════════════════════ -->
    <div class="absolute h-[1080px] left-0 top-0 w-[76px]">
      <div class="absolute bg-[#1e1e1e] inset-[0_0_-14.17%_0]" />
      <!-- Group47 → Group45 → Group8 (home icon, white fill) -->
      <div class="absolute inset-[16.76%_31.58%_80.74%_32.89%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27 27">
          <g id="Group 372hp">
            <path :d="svgPaths.pfb9b730" fill="var(--fill-0, white)" id="Vector" />
          </g>
        </svg>
      </div>
      <!-- Calendar icon (white fill) -->
      <div class="absolute inset-[23.52%_31.58%_74.07%_32.89%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27 26">
          <g id="calendar-hp">
            <g :clip-path="'url(#clip0_6_1121)'">
              <path :d="svgPaths.p26b4fe00" fill="var(--fill-0, white)" id="Vector" />
              <path :d="svgPaths.p2e434e00" fill="var(--fill-0, white)" id="Vector_2" />
              <path :d="svgPaths.pbd5a100" fill="var(--fill-0, white)" id="Vector_3" />
              <path :d="svgPaths.p7923600" fill="var(--fill-0, white)" id="Vector_4" />
              <path :d="svgPaths.peff05f1" fill="var(--fill-0, white)" id="Vector_5" />
              <path :d="svgPaths.p25bf2b00" fill="var(--fill-0, white)" id="Vector_6" />
            </g>
          </g>
          <defs>
            <clipPath id="clip0_6_1121">
              <path d="M0 0H27V26H0V0Z" fill="white" />
            </clipPath>
          </defs>
        </svg>
      </div>
      <!-- FolderAdd icon (white fill) -->
      <div class="absolute inset-[30.65%_32.89%_67.04%_31.58%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27 25">
          <g id="folder-add-hp">
            <g :clip-path="'url(#clip0_6_1140)'">
              <path :d="svgPaths.p73efb00" fill="var(--fill-0, white)" id="Vector" />
              <path :d="svgPaths.p1c6b8300" fill="var(--fill-0, white)" id="Vector_2" />
            </g>
          </g>
          <defs>
            <clipPath id="clip0_6_1140">
              <path d="M0 0H27V25H0V0Z" fill="white" />
            </clipPath>
          </defs>
        </svg>
      </div>
      <!-- Group74: Tuts logo (white fill) -->
      <div class="absolute inset-[37.96%_27.63%_59.36%_36.84%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27.0001 28.8687">
          <g id="Group 614hp">
            <path :d="svgPaths.pbbb1f40" fill="var(--fill-0, white)" id="Vector" />
            <path :d="svgPaths.p2c0c0780" fill="var(--fill-0, white)" id="Vector_2" />
            <path :d="svgPaths.p2c791300" fill="var(--fill-0, white)" id="Vector_3" />
            <path :d="svgPaths.p2b5127f2" fill="var(--fill-0, white)" id="Vector_4" />
            <path :d="svgPaths.pdbdce70" fill="var(--fill-0, #FEFEFE)" id="Vector_5" />
            <path :d="svgPaths.p15666d00" fill="var(--fill-0, white)" id="Vector_6" />
            <path :d="svgPaths.p3e579600" fill="var(--fill-0, #FEFEFE)" id="Vector_7" />
          </g>
        </svg>
      </div>
      <!-- Logout: empty positioned div -->
      <div class="absolute inset-[74.26%_-1130.26%_11.3%_1046.05%]" />
      <!-- Group140: profile icon (white fill) -->
      <div class="absolute inset-[91.85%_25.35%_5.19%_36.84%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 28.7347 32">
          <g id="Group 683hp">
            <path :d="svgPaths.p20771200" fill="var(--fill-0, white)" id="Vector" />
            <path :d="svgPaths.p19644800" fill="var(--fill-0, white)" id="Vector_2" />
          </g>
        </svg>
      </div>
    </div><!-- /Sidebar -->

    <!-- ═══════════════════════════════════════════════════════════════
         Nav bar (absolute children of outer Homepage div)
         ═══════════════════════════════════════════════════════════════ -->
    <!-- "Responsible AI" text -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Medium',sans-serif] font-medium h-[31px] justify-center leading-[0] left-[calc(50%+664.5px)] not-italic text-[11px] text-black text-center top-[62.5px] tracking-[0.33px] w-[121px]">
      <p class="leading-[normal]">Responsible AI</p>
    </div>
    <!-- Info icon (rotated) -->
    <div class="absolute flex h-[17px] items-center justify-center left-[1556px] top-[55px] w-[17.45px]">
      <div class="-scale-y-100 flex-none rotate-180">
        <div class="h-[17px] relative w-[17.45px]">
          <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 17.4497 17">
            <g id="Info-hp">
              <path :d="svgPaths.p5490e32" fill="var(--fill-0, black)" id="Vector" />
              <path :d="svgPaths.p2c6cab00" fill="var(--fill-0, black)" id="Vector_2" />
              <path :d="svgPaths.p2205ec80" fill="var(--fill-0, black)" id="Vector_3" />
            </g>
          </svg>
        </div>
      </div>
    </div>
    <!-- Toggle switch (Group5 → Group3) -->
    <div class="absolute h-[23px] left-[1488px] top-[52px] w-[55px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 55 23">
        <g id="Group 369hp">
          <rect fill="var(--fill-0, white)" height="22" id="Rectangle 143" rx="11" stroke="var(--stroke-0, #54A64E)" width="54" x="0.5" y="0.5" />
          <circle cx="11.5" cy="11.5" fill="var(--fill-0, #54A64E)" id="Ellipse 873" r="8" stroke="var(--stroke-0, #54A64E)" />
          <path :d="svgPaths.p2df65710" fill="var(--fill-0, #A7C68E)" id="tick" stroke="var(--stroke-0, #54A64E)" stroke-width="0.656338" />
        </g>
      </svg>
    </div>
    <!-- Sunny icon -->
    <div class="absolute h-[16px] left-[1460px] top-[56px] w-[17px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 17 16">
        <g :clip-path="'url(#clip0_6_1102)'" id="sunny-hp">
          <path :d="svgPaths.p1b45bb80" fill="var(--fill-0, #54A64E)" id="Vector" />
          <path :d="svgPaths.p13f41c00" fill="var(--fill-0, #54A64E)" id="Vector_2" />
          <path :d="svgPaths.p23bf7ec0" fill="var(--fill-0, #54A64E)" id="Vector_3" />
          <path :d="svgPaths.p19c4b500" fill="var(--fill-0, #54A64E)" id="Vector_4" />
          <path :d="svgPaths.p24850200" fill="var(--fill-0, #54A64E)" id="Vector_5" />
          <path :d="svgPaths.p3501b810" fill="var(--fill-0, #54A64E)" id="Vector_6" />
          <path :d="svgPaths.p25239980" fill="var(--fill-0, #54A64E)" id="Vector_7" />
          <path :d="svgPaths.p16fd9ec0" fill="var(--fill-0, #54A64E)" id="Vector_8" />
          <path :d="svgPaths.p15728940" fill="var(--fill-0, #54A64E)" id="Vector_9" />
        </g>
        <defs>
          <clipPath id="clip0_6_1102">
            <rect fill="white" height="16" width="17" />
          </clipPath>
        </defs>
      </svg>
    </div>
    <!-- Avatar circle -->
    <div class="absolute left-[1733px] size-[43px] top-[37px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 43 43">
        <circle cx="21.5" cy="21.5" fill="var(--fill-0, #DF32AB)" id="Ellipse 871hp" r="21.5" />
      </svg>
    </div>
    <!-- "M" initial -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[1755px] not-italic size-[28px] text-[24px] text-center text-white top-[58px]">
      <p class="leading-[normal]">M</p>
    </div>
    <!-- "MARIA FRIAS" -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[1828.5px] not-italic text-[13px] text-black text-center top-[54px] whitespace-nowrap">
      <p class="leading-[normal]">MARIA FRIAS</p>
    </div>
    <!-- "Estudante" -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Italic',sans-serif] font-normal italic justify-center leading-[0] left-[1810px] text-[#bdbaba] text-[10px] text-center top-[68px] whitespace-nowrap">
      <p class="leading-[normal]">Estudante</p>
    </div>
    <!-- Bell icon -->
    <div class="absolute h-[26px] left-[1697px] top-[46px] w-[25px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 25 26">
        <g :clip-path="'url(#clip0_6_1093)'" id="bell-2-hp">
          <path :d="svgPaths.p20627470" fill="var(--fill-0, black)" id="Vector" />
        </g>
        <defs>
          <clipPath id="clip0_6_1093">
            <rect fill="white" height="26" width="25" />
          </clipPath>
        </defs>
      </svg>
    </div>
    <!-- Group1: Tut's logo text (green paths) -->
    <div class="absolute inset-[4.17%_46.46%_94%_50.47%]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 59 19.8371">
        <g id="Group 275hp">
          <path :d="svgPaths.pd9e55f1" fill="var(--fill-0, #54A64E)" id="Vector" />
          <path :d="svgPaths.p1b6ee280" fill="var(--fill-0, #54A64E)" id="Vector_2" />
          <path :d="svgPaths.p3d974a00" fill="var(--fill-0, #54A64E)" id="Vector_3" />
          <path :d="svgPaths.p13434c00" fill="var(--fill-0, #54A64E)" id="Vector_4" />
          <path :d="svgPaths.p8dc1d00" fill="var(--fill-0, #54A64E)" id="Vector_5" />
        </g>
      </svg>
    </div>
    <!-- Semester label -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[564px] not-italic text-[#bdbaba] text-[11px] top-[209.5px] whitespace-nowrap">
      <p class="leading-[normal] whitespace-pre">1 semestre -  Ano letivo 2026/2027</p>
    </div>
    <!-- Subtitle -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[139px] not-italic text-[#bdbaba] text-[16px] top-[126.5px] whitespace-nowrap">
      <p class="leading-[normal]">Continua o teu plano de estudos e consulta os novos materiais verificados</p>
    </div>
    <!-- "Bem vinda, Maria!" heading -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[278px] not-italic text-[32px] text-black text-center top-[97.5px] whitespace-nowrap">
      <p class="leading-[normal]">Bem vinda, Maria!</p>
    </div>

  </div><!-- /Homepage -->
</template>

<script setup lang="ts">
import svgPaths from './svg-88rflhvsvh'
import { imgSusanQYin2JIvboGLehoUnsplash1 } from './svg-uhtdi'
import imgSusanQYin2JIvboGLehoUnsplash2 from './d7384d8b80435b7be09597b2fd4ad37214583c90.png'
import imgSusanQYin2JIvboGLehoUnsplash3 from './a9f726855390276df79bc12e8b5f0e16e8f130a4.png'
import imgSusanQYin2JIvboGLehoUnsplash4 from './47a662d5d00ca6c21a2fe73787575dce767c4f67.png'
import imgDecaPtHorizontalExtensaPreto1 from './bcf29d3acd99bf822058de8e42a68de0d6a6f843.png'
import imgCapturaDeEcra20260421As1946111 from './8d930c26df9d64cb4a5b9299291b4e6a16b24965.png'

defineOptions({ name: 'HomepageFrame' })
</script>
