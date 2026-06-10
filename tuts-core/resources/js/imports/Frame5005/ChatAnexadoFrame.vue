<template>
  <div class="absolute bg-[#faf9f6] h-[1080px] left-[126px] overflow-clip top-[1576px] w-[1920px]">

    <!-- White card (base layer at top-[395px]) -->
    <div class="absolute bg-white left-[calc(8.33%-3px)] rounded-[22px] shadow-[0px_3px_14.7px_-3px_rgba(0,0,0,0.25)] size-[250px] top-[395px]" />

    <!-- Green gradient card (top-[667px]) -->
    <div
      class="absolute left-[calc(8.33%-3px)] rounded-[22px] shadow-[0px_3px_14.7px_-3px_rgba(0,0,0,0.25)] size-[250px] top-[667px]"
      style="background-image: linear-gradient(90deg, rgba(255, 255, 255, 0.44) 0%, rgba(255, 255, 255, 0.44) 100%), linear-gradient(rgba(167, 198, 142, 0.16) 0%, rgba(0, 0, 0, 0.16) 100%), linear-gradient(90deg, rgb(84, 166, 78) 0%, rgb(84, 166, 78) 100%)"
    />

    <!-- Sidebar -->
    <div class="absolute h-[1080px] left-0 top-[-28px] w-[76px]">
      <div class="absolute bg-[#1e1e1e] inset-[0_0_-14.17%_0]" />

      <!-- Group15: Home icon FAF9F6 -->
      <div class="absolute inset-[16.76%_31.58%_80.74%_32.89%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27 27">
          <g id="Group372-ca">
            <path :d="svgPaths.pfb9b730" fill="var(--fill-0, #FAF9F6)" id="Vector-ca" />
          </g>
        </svg>
      </div>

      <!-- Calendar2: FAF9F6 fill -->
      <div class="absolute inset-[23.52%_31.58%_74.07%_32.89%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27 26">
          <g id="calendar-ca">
            <g :clip-path="'url(#clip0_6_1012-ca)'">
              <path :d="svgPaths.p26b4fe00" fill="var(--fill-0, #FAF9F6)" id="Vector-ca2" />
              <path :d="svgPaths.p2e434e00" fill="var(--fill-0, #FAF9F6)" id="Vector_2-ca2" />
              <path :d="svgPaths.pbd5a100" fill="var(--fill-0, #FAF9F6)" id="Vector_3-ca2" />
              <path :d="svgPaths.p7923600" fill="var(--fill-0, #FAF9F6)" id="Vector_4-ca2" />
              <path :d="svgPaths.peff05f1" fill="var(--fill-0, #FAF9F6)" id="Vector_5-ca2" />
              <path :d="svgPaths.p25bf2b00" fill="var(--fill-0, #FAF9F6)" id="Vector_6-ca2" />
            </g>
          </g>
          <defs>
            <clipPath id="clip0_6_1012-ca">
              <path d="M0 0H27V26H0V0Z" fill="white" />
            </clipPath>
          </defs>
        </svg>
      </div>

      <!-- FolderAdd2: FAF9F6 fill -->
      <div class="absolute inset-[30.65%_32.89%_67.04%_31.58%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27 25">
          <g id="folder-add-ca">
            <g :clip-path="'url(#clip0_6_1045-ca)'">
              <path :d="svgPaths.p73efb00" fill="var(--fill-0, #FAF9F6)" id="Vector-ca3" />
              <path :d="svgPaths.p1c6b8300" fill="var(--fill-0, #FAF9F6)" id="Vector_2-ca3" />
            </g>
          </g>
          <defs>
            <clipPath id="clip0_6_1045-ca">
              <path d="M0 0H27V25H0V0Z" fill="white" />
            </clipPath>
          </defs>
        </svg>
      </div>

      <!-- Group79: Tuts logo white fill -->
      <div class="absolute inset-[37.96%_27.63%_59.36%_36.84%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27 28.8686">
          <g id="Group614-ca">
            <path :d="svgPaths.pbbb1f40" fill="var(--fill-0, white)" id="Vector-ca4" />
            <path :d="svgPaths.p2c0c0780" fill="var(--fill-0, white)" id="Vector_2-ca4" />
            <path :d="svgPaths.p2c791300" fill="var(--fill-0, white)" id="Vector_3-ca4" />
            <path :d="svgPaths.p2b5127f2" fill="var(--fill-0, white)" id="Vector_4-ca4" />
            <path :d="svgPaths.pdbdce70" fill="var(--fill-0, #FEFEFE)" id="Vector_5-ca4" />
            <path :d="svgPaths.p15666d00" fill="var(--fill-0, white)" id="Vector_6-ca4" />
            <path :d="svgPaths.p3e579600" fill="var(--fill-0, #FEFEFE)" id="Vector_7-ca4" />
          </g>
        </svg>
      </div>

      <!-- Logout2: empty div -->
      <div class="absolute inset-[74.26%_-1130.26%_11.3%_1046.05%]" />

      <!-- Group142: profile icon white fill -->
      <div class="absolute inset-[91.85%_25.35%_5.19%_36.84%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 28.7347 32">
          <g id="Group683-ca">
            <path :d="svgPaths.p20771200" fill="var(--fill-0, white)" id="Vector-ca5" />
            <path :d="svgPaths.p19644800" fill="var(--fill-0, white)" id="Vector_2-ca5" />
          </g>
        </svg>
      </div>
    </div>

    <!-- Green gradient card (top-[664px], layered below/above) -->
    <div
      class="absolute left-[calc(8.33%-3px)] rounded-[22px] shadow-[0px_3px_14.7px_-3px_rgba(0,0,0,0.25)] size-[250px] top-[664px]"
      style="background-image: linear-gradient(90deg, rgba(255, 255, 255, 0.71) 0%, rgba(255, 255, 255, 0.71) 100%), linear-gradient(90deg, rgba(255, 255, 255, 0.66) 0%, rgba(255, 255, 255, 0.66) 100%), linear-gradient(90deg, rgba(255, 255, 255, 0.32) 0%, rgba(255, 255, 255, 0.32) 100%), linear-gradient(rgb(108, 178, 149) 0%, rgba(84, 166, 78, 0.16) 100%)"
    />

    <!-- Group81: Mini Calendar -->
    <!-- Header row -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+29px)] not-italic text-[13px] text-black top-[434px] whitespace-nowrap">
      <p>
        <span class="leading-[normal] text-[#54a64e]">ABRIL</span>
        <span class="leading-[normal]">, 2026</span>
      </p>
    </div>
    <!-- Day headers -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+31px)] not-italic text-[#736d6d] text-[13px] top-[469px] whitespace-nowrap">
      <p class="leading-[normal]">D</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+61px)] not-italic text-[13px] text-black top-[469px] whitespace-nowrap">
      <p class="leading-[normal]">S</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+90px)] not-italic text-[13px] text-black top-[469px] whitespace-nowrap">
      <p class="leading-[normal]">T</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+119px)] not-italic text-[13px] text-black top-[469px] whitespace-nowrap">
      <p class="leading-[normal]">Q</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+150px)] not-italic text-[13px] text-black top-[469px] whitespace-nowrap">
      <p class="leading-[normal]">Q</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(16.67%+23px)] not-italic text-[#736d6d] text-[13px] top-[469px] whitespace-nowrap">
      <p class="leading-[normal]">S</p>
    </div>
    <!-- Row 1: 29 30 31 1 2 3 -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+28px)] not-italic text-[#736d6d] text-[13px] top-[492px] whitespace-nowrap">
      <p class="leading-[normal]">29</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+57px)] not-italic text-[13px] text-black top-[493px] whitespace-nowrap">
      <p class="leading-[normal]">30</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+88px)] not-italic text-[13px] text-black top-[493px] whitespace-nowrap">
      <p class="leading-[normal]">31</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+119px)] not-italic text-[13px] text-black top-[493px] whitespace-nowrap">
      <p class="leading-[normal]">1</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+150px)] not-italic text-[13px] text-black top-[493px] whitespace-nowrap">
      <p class="leading-[normal]">2</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(16.67%+23px)] not-italic text-[#736d6d] text-[13px] top-[492px] whitespace-nowrap">
      <p class="leading-[normal]">3</p>
    </div>
    <!-- Row 2: 5 6 [7 circled] 8 9 10 -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+32px)] not-italic text-[#736d6d] text-[13px] top-[515px] whitespace-nowrap">
      <p class="leading-[normal]">5</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+60px)] not-italic text-[13px] text-black top-[516px] whitespace-nowrap">
      <p class="leading-[normal]">6</p>
    </div>
    <div class="absolute left-[calc(8.33%+84px)] size-[21px] top-[505px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 21 21">
        <circle cx="10.5" cy="10.5" fill="var(--fill-0, #54A64E)" id="Ellipse899-ca" r="10.5" />
      </svg>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+91px)] not-italic text-[13px] text-white top-[516px] whitespace-nowrap">
      <p class="leading-[normal]">7</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+119px)] not-italic text-[13px] text-black top-[516px] whitespace-nowrap">
      <p class="leading-[normal]">8</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+150px)] not-italic text-[13px] text-black top-[516px] whitespace-nowrap">
      <p class="leading-[normal]">9</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(16.67%+19px)] not-italic text-[#736d6d] text-[13px] top-[515px] whitespace-nowrap">
      <p class="leading-[normal]">10</p>
    </div>
    <!-- Row 3: 12 13 14 15 16 17 -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+29px)] not-italic text-[#736d6d] text-[13px] top-[538px] whitespace-nowrap">
      <p class="leading-[normal]">12</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+57px)] not-italic text-[13px] text-black top-[538px] whitespace-nowrap">
      <p class="leading-[normal]">13</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+87px)] not-italic text-[13px] text-black top-[538px] whitespace-nowrap">
      <p class="leading-[normal]">14</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+119px)] not-italic text-[13px] text-black top-[538px] whitespace-nowrap">
      <p class="leading-[normal]">15</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+150px)] not-italic text-[13px] text-black top-[538px] whitespace-nowrap">
      <p class="leading-[normal]">16</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(16.67%+20px)] not-italic text-[#736d6d] text-[13px] top-[538px] whitespace-nowrap">
      <p class="leading-[normal]">17</p>
    </div>
    <!-- Row 4: 12 13 14 15 16 17 (repeated in source) -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+29px)] not-italic text-[#736d6d] text-[13px] top-[561px] whitespace-nowrap">
      <p class="leading-[normal]">12</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+57px)] not-italic text-[13px] text-black top-[561px] whitespace-nowrap">
      <p class="leading-[normal]">13</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+87px)] not-italic text-[13px] text-black top-[561px] whitespace-nowrap">
      <p class="leading-[normal]">14</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+119px)] not-italic text-[13px] text-black top-[561px] whitespace-nowrap">
      <p class="leading-[normal]">15</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+150px)] not-italic text-[13px] text-black top-[561px] whitespace-nowrap">
      <p class="leading-[normal]">16</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(16.67%+20px)] not-italic text-[#736d6d] text-[13px] top-[561px] whitespace-nowrap">
      <p class="leading-[normal]">17</p>
    </div>
    <!-- Row 5: 12 13 14 15 16 17 (repeated in source) -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+29px)] not-italic text-[#736d6d] text-[13px] top-[584px] whitespace-nowrap">
      <p class="leading-[normal]">12</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+57px)] not-italic text-[13px] text-black top-[587px] whitespace-nowrap">
      <p class="leading-[normal]">13</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+87px)] not-italic text-[13px] text-black top-[587px] whitespace-nowrap">
      <p class="leading-[normal]">14</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+119px)] not-italic text-[13px] text-black top-[587px] whitespace-nowrap">
      <p class="leading-[normal]">15</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+150px)] not-italic text-[13px] text-black top-[587px] whitespace-nowrap">
      <p class="leading-[normal]">16</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(16.67%+20px)] not-italic text-[#736d6d] text-[13px] top-[584px] whitespace-nowrap">
      <p class="leading-[normal]">17</p>
    </div>
    <!-- Row 6: 12 13 14 15 16 17 (repeated in source) -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+29px)] not-italic text-[#736d6d] text-[13px] top-[607px] whitespace-nowrap">
      <p class="leading-[normal]">12</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+57px)] not-italic text-[13px] text-black top-[613px] whitespace-nowrap">
      <p class="leading-[normal]">13</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+87px)] not-italic text-[13px] text-black top-[613px] whitespace-nowrap">
      <p class="leading-[normal]">14</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+119px)] not-italic text-[13px] text-black top-[613px] whitespace-nowrap">
      <p class="leading-[normal]">15</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(8.33%+150px)] not-italic text-[13px] text-black top-[613px] whitespace-nowrap">
      <p class="leading-[normal]">16</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(16.67%+21px)] not-italic text-[#736d6d] text-[13px] top-[613px] whitespace-nowrap">
      <p class="leading-[normal]">17</p>
    </div>

    <!-- "Eventos próximos" heading -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Extra_Bold',sans-serif] font-extrabold justify-center leading-[0] left-[calc(8.33%+8px)] not-italic text-[#54a64e] text-[16px] top-[712.5px] whitespace-nowrap">
      <p class="leading-[normal]">Eventos próximos</p>
    </div>

    <!-- Event row 1: 9:00 #E96262 -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+20px)] not-italic text-[13px] text-black top-[758px] whitespace-nowrap">
      <p class="leading-[normal]">9:00</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+92px)] not-italic text-[13px] text-black top-[758px] whitespace-nowrap">
      <p class="leading-[normal]">Teste de TACS</p>
    </div>
    <div class="absolute left-[calc(8.33%+70px)] size-[8px] top-[754px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 8 8">
        <circle cx="4" cy="4" fill="var(--fill-0, #E96262)" id="Ellipse903-ca" r="4" />
      </svg>
    </div>

    <!-- Event row 2: 10:00 #E96262 -->
    <div class="absolute left-[calc(8.33%+70px)] size-[8px] top-[785px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 8 8">
        <circle cx="4" cy="4" fill="var(--fill-0, #E96262)" id="Ellipse903b-ca" r="4" />
      </svg>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+20px)] not-italic text-[13px] text-black top-[789px] whitespace-nowrap">
      <p class="leading-[normal]">10:00</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+92px)] not-italic text-[13px] text-black top-[789px] whitespace-nowrap">
      <p class="leading-[normal]">Teste de TACS</p>
    </div>

    <!-- Event row 3: 11:00 #A7C68E -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+20px)] not-italic text-[13px] text-black top-[820px] whitespace-nowrap">
      <p class="leading-[normal]">11:00</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+92px)] not-italic text-[13px] text-black top-[820px] whitespace-nowrap">
      <p class="leading-[normal]">Teste de TACS</p>
    </div>
    <div class="absolute left-[calc(8.33%+70px)] size-[8px] top-[816px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 8 8">
        <circle cx="4" cy="4" fill="var(--fill-0, #A7C68E)" id="Ellipse905-ca" r="4" />
      </svg>
    </div>

    <!-- Event row 4: 12:00 #528950 -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+20px)] not-italic text-[13px] text-black top-[851px] whitespace-nowrap">
      <p class="leading-[normal]">12:00</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+92px)] not-italic text-[13px] text-black top-[851px] whitespace-nowrap">
      <p class="leading-[normal]">Teste de TACS</p>
    </div>
    <div class="absolute left-[calc(8.33%+70px)] size-[8px] top-[847px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 8 8">
        <circle cx="4" cy="4" fill="var(--fill-0, #528950)" id="Ellipse906-ca" r="4" />
      </svg>
    </div>

    <!-- Green gradient card (Lembretes, top-[132px]) -->
    <div
      class="absolute left-[calc(8.33%-2px)] rounded-[22px] shadow-[0px_3px_14.7px_-3px_rgba(0,0,0,0.25)] size-[250px] top-[132px]"
      style="background-image: linear-gradient(90deg, rgba(255, 255, 255, 0.71) 0%, rgba(255, 255, 255, 0.71) 100%), linear-gradient(90deg, rgba(255, 255, 255, 0.66) 0%, rgba(255, 255, 255, 0.66) 100%), linear-gradient(90deg, rgba(255, 255, 255, 0.32) 0%, rgba(255, 255, 255, 0.32) 100%), linear-gradient(rgb(108, 178, 149) 0%, rgba(84, 166, 78, 0.16) 100%)"
    />

    <!-- Lembretes circles (4 reminder items) -->
    <div class="absolute left-[calc(8.33%+8px)] size-[21px] top-[217px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 21 21">
        <circle cx="10.5" cy="10.5" id="Ellipse900-ca" r="10" stroke="var(--stroke-0, #1E1E1E)" />
      </svg>
    </div>
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+68.5px)] not-italic text-[#1e1e1e] text-[12px] text-center top-[225.5px] whitespace-nowrap">
      <p class="leading-[1.4]">Lembretes</p>
    </div>
    <div class="absolute left-[calc(8.33%+8px)] size-[21px] top-[250px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 21 21">
        <circle cx="10.5" cy="10.5" id="Ellipse900b-ca" r="10" stroke="var(--stroke-0, #1E1E1E)" />
      </svg>
    </div>
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+68.5px)] not-italic text-[#1e1e1e] text-[12px] text-center top-[258.5px] whitespace-nowrap">
      <p class="leading-[1.4]">Lembretes</p>
    </div>
    <div class="absolute left-[calc(8.33%+8px)] size-[21px] top-[283px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 21 21">
        <circle cx="10.5" cy="10.5" id="Ellipse900c-ca" r="10" stroke="var(--stroke-0, #1E1E1E)" />
      </svg>
    </div>
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+68.5px)] not-italic text-[#1e1e1e] text-[12px] text-center top-[291.5px] whitespace-nowrap">
      <p class="leading-[1.4]">Lembretes</p>
    </div>
    <div class="absolute left-[calc(8.33%+8px)] size-[21px] top-[316px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 21 21">
        <circle cx="10.5" cy="10.5" id="Ellipse900d-ca" r="10" stroke="var(--stroke-0, #1E1E1E)" />
      </svg>
    </div>
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(8.33%+68.5px)] not-italic text-[#1e1e1e] text-[12px] text-center top-[324.5px] whitespace-nowrap">
      <p class="leading-[1.4]">Lembretes</p>
    </div>

    <!-- Lembretes heading -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Extra_Bold',sans-serif] font-extrabold justify-center leading-[0] left-[calc(8.33%+14px)] not-italic text-[#54a64e] text-[16px] top-[177.5px] whitespace-nowrap">
      <p class="leading-[normal]">Lembretes</p>
    </div>
    <!-- "3" count -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(16.67%+23px)] not-italic text-[#1e1e1e] text-[32px] top-[168.5px] whitespace-nowrap">
      <p class="leading-[normal]">3</p>
    </div>
    <!-- Separator line below heading -->
    <div class="absolute h-0 left-[calc(8.33%+1px)] top-[192px] w-[216px]">
      <div class="absolute inset-[-1px_0_0_0]">
        <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 216 1">
          <line id="Line68a-ca" stroke="var(--stroke-0, #1E1E1E)" stroke-opacity="0.06" x2="216" y1="0.5" y2="0.5" />
        </svg>
      </div>
    </div>

    <!-- Nav bar: Responsible AI -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Medium',sans-serif] font-medium h-[31px] justify-center leading-[0] left-[calc(87.5%-55.5px)] not-italic text-[11px] text-black text-center top-[62.5px] tracking-[0.33px] w-[121px]">
      <p class="leading-[normal]">Responsible AI</p>
    </div>

    <!-- Info3: rotated info icon -->
    <div class="absolute flex h-[17px] items-center justify-center left-[calc(75%+116px)] top-[55px] w-[17.45px]">
      <div class="-scale-y-100 flex-none rotate-180">
        <div class="h-[17px] relative w-[17.45px]">
          <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 17.4497 17">
            <g id="Info-ca">
              <path :d="svgPaths.p5490e32" fill="var(--fill-0, black)" id="VectorInfo-ca" />
              <path :d="svgPaths.p2c6cab00" fill="var(--fill-0, black)" id="VectorInfo2-ca" />
              <path :d="svgPaths.p2205ec80" fill="var(--fill-0, black)" id="VectorInfo3-ca" />
            </g>
          </svg>
        </div>
      </div>
    </div>

    <!-- Group17 (toggle switch) -->
    <div class="absolute h-[23px] left-[calc(75%+48px)] top-[52px] w-[55px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 55 23">
        <g id="Group369-ca">
          <rect fill="var(--fill-0, white)" height="22" id="Rectangle143-ca" rx="11" stroke="var(--stroke-0, #54A64E)" width="54" x="0.5" y="0.5" />
          <circle cx="11.5" cy="11.5" fill="var(--fill-0, #54A64E)" id="Ellipse873-ca" r="8" stroke="var(--stroke-0, #54A64E)" />
          <path :d="svgPaths.p2df65710" fill="var(--fill-0, #A7C68E)" id="tickToggle-ca" stroke="var(--stroke-0, #54A64E)" stroke-width="0.656338" />
        </g>
      </svg>
    </div>

    <!-- Sunny3: sun icon -->
    <div class="absolute h-[16px] left-[calc(75%+20px)] top-[56px] w-[17px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 17 16">
        <g :clip-path="'url(#clip0_6_1102-ca)'" id="sunny-ca">
          <path :d="svgPaths.p1b45bb80" fill="var(--fill-0, #54A64E)" id="VectorSun-ca" />
          <path :d="svgPaths.p13f41c00" fill="var(--fill-0, #54A64E)" id="VectorSun2-ca" />
          <path :d="svgPaths.p23bf7ec0" fill="var(--fill-0, #54A64E)" id="VectorSun3-ca" />
          <path :d="svgPaths.p19c4b500" fill="var(--fill-0, #54A64E)" id="VectorSun4-ca" />
          <path :d="svgPaths.p24850200" fill="var(--fill-0, #54A64E)" id="VectorSun5-ca" />
          <path :d="svgPaths.p3501b810" fill="var(--fill-0, #54A64E)" id="VectorSun6-ca" />
          <path :d="svgPaths.p25239980" fill="var(--fill-0, #54A64E)" id="VectorSun7-ca" />
          <path :d="svgPaths.p16fd9ec0" fill="var(--fill-0, #54A64E)" id="VectorSun8-ca" />
          <path :d="svgPaths.p15728940" fill="var(--fill-0, #54A64E)" id="VectorSun9-ca" />
        </g>
        <defs>
          <clipPath id="clip0_6_1102-ca">
            <rect fill="white" height="16" width="17" />
          </clipPath>
        </defs>
      </svg>
    </div>

    <!-- Avatar circle #DF32AB -->
    <div class="absolute left-[calc(91.67%-27px)] size-[43px] top-[37px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 43 43">
        <circle cx="21.5" cy="21.5" fill="var(--fill-0, #DF32AB)" id="Ellipse871-ca" r="21.5" />
      </svg>
    </div>
    <!-- "M" initial -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(91.67%-5px)] not-italic size-[28px] text-[24px] text-center text-white top-[58px]">
      <p class="leading-[normal]">M</p>
    </div>
    <!-- "MARIA FRIAS" -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(91.67%+68.5px)] not-italic text-[13px] text-black text-center top-[54px] whitespace-nowrap">
      <p class="leading-[normal]">MARIA FRIAS</p>
    </div>
    <!-- "Estudante" -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Italic',sans-serif] font-normal italic justify-center leading-[0] left-[calc(91.67%+50px)] text-[#bdbaba] text-[10px] text-center top-[68px] whitespace-nowrap">
      <p class="leading-[normal]">Estudante</p>
    </div>

    <!-- Bell2 -->
    <div class="absolute h-[26px] left-[calc(83.33%+97px)] top-[46px] w-[25px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 25 26">
        <g :clip-path="'url(#clip0_6_1093-ca)'" id="bell-2-ca">
          <path :d="svgPaths.p20627470" fill="var(--fill-0, black)" id="VectorBell-ca" />
        </g>
        <defs>
          <clipPath id="clip0_6_1093-ca">
            <rect fill="white" height="26" width="25" />
          </clipPath>
        </defs>
      </svg>
    </div>

    <!-- Separator line at top-[727px] -->
    <div class="absolute h-0 left-[8.33%] top-[727px] w-[216px]">
      <div class="absolute inset-[-1px_0_0_0]">
        <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 216 1">
          <line id="Line68b-ca" stroke="var(--stroke-0, #1E1E1E)" stroke-opacity="0.06" x2="216" y1="0.5" y2="0.5" />
        </svg>
      </div>
    </div>

    <!-- Gradient divider at top-[114px] -->
    <div class="absolute h-0 left-[147px] top-[114px] w-[1741px]">
      <div class="absolute inset-[-1px_0_0_0]">
        <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 1741 1">
          <line id="Line68c-ca" stroke="url(#paint0_linear_6_987-ca)" x2="1741" y1="0.5" y2="0.5" />
          <defs>
            <linearGradient gradient-units="userSpaceOnUse" id="paint0_linear_6_987-ca" x1="0" x2="1741" y1="1.5" y2="1.5">
              <stop offset="0.182692" stop-color="#6CB295" />
              <stop offset="0.514423" stop-color="#1E1E1E" />
              <stop offset="0.822115" stop-color="#6CB295" />
            </linearGradient>
          </defs>
        </svg>
      </div>
    </div>

    <!-- Group94: "@Tacs" green pill -->
    <div class="absolute bg-[#cfe9ce] h-[43px] left-[calc(83.33%+102px)] rounded-[10px] top-[129px] w-[118px]" />
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[16px] justify-center leading-[0] left-[calc(91.67%+0.5px)] not-italic text-[20px] text-black text-center top-[150px] w-[151px]">
      <p class="leading-[normal]">@Tacs</p>
    </div>

    <!-- Group95: "/aulas teóricas" green pill -->
    <div class="absolute bg-[#cfe9ce] h-[43px] left-[calc(75%+88px)] rounded-[10px] top-[129px] w-[163px]" />
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[16px] justify-center leading-[0] left-[calc(87.5%-70.5px)] not-italic text-[20px] text-black text-center top-[151px] w-[151px]">
      <p class="leading-[normal]">/aulas teóricas</p>
    </div>

    <!-- Group96: "Conversa" tab + chat icon (Group84) -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Medium',sans-serif] font-medium justify-center leading-[0] left-[calc(29.17%-17px)] not-italic text-[#1e1e1e] text-[14px] top-[94px] tracking-[-0.14px] whitespace-nowrap">
      <p class="leading-[40px]">Conversa</p>
    </div>
    <!-- Group84: chat icon at inset-[7.96%_72.36%_90.38%_26.77%] -->
    <div class="absolute inset-[7.96%_72.36%_90.38%_26.77%]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 16.7107 17.8682">
        <g id="Group614b-ca">
          <path :d="svgPaths.p2918d980" fill="var(--fill-0, #1E1E1E)" id="VectorChat-ca" />
          <path :d="svgPaths.p39604500" fill="var(--fill-0, #1E1E1E)" id="VectorChat2-ca" />
          <path :d="svgPaths.p216ccb20" fill="var(--fill-0, #1E1E1E)" id="VectorChat3-ca" />
          <path :d="svgPaths.p26b7bd80" fill="var(--fill-0, #1E1E1E)" id="VectorChat4-ca" />
          <path :d="svgPaths.p34966c0" fill="var(--fill-0, #1E1E1E)" id="VectorChat5-ca" />
          <path :d="svgPaths.pfd5d400" fill="var(--fill-0, #1E1E1E)" id="VectorChat6-ca" />
          <path :d="svgPaths.p1086d200" fill="var(--fill-0, #1E1E1E)" id="VectorChat7-ca" />
        </g>
      </svg>
    </div>

    <!-- "Materiais oficiais" tab -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Medium',sans-serif] font-medium justify-center leading-[0] left-[calc(37.5%-50px)] not-italic text-[#1e1e1e] text-[14px] top-[94px] tracking-[-0.14px] whitespace-nowrap">
      <p class="leading-[40px]">Materiais oficiais</p>
    </div>
    <!-- "Os meus materiais" tab -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Medium',sans-serif] font-medium justify-center leading-[0] left-[calc(45.83%-58px)] not-italic text-[#1e1e1e] text-[14px] top-[92px] tracking-[-0.14px] whitespace-nowrap">
      <p class="leading-[40px]">Os meus materiais</p>
    </div>
    <!-- Tab underline -->
    <div class="absolute h-0 left-[calc(25%+24px)] top-[115px] w-[125px]">
      <div class="absolute inset-[-2.5px_0_0_0]">
        <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 125 2.5">
          <line id="Line69-ca" stroke="var(--stroke-0, black)" stroke-width="2.5" x2="125" y1="1.25" y2="1.25" />
        </svg>
      </div>
    </div>

    <!-- Green arrow circle -->
    <div class="absolute left-[calc(91.67%+80px)] size-[37px] top-[132px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 37 37">
        <circle cx="18.5" cy="18.5" fill="var(--fill-0, #A7C68E)" id="Ellipse916-ca" r="18.5" />
      </svg>
    </div>

    <!-- Group64: 3-dot button (rotated 180°) -->
    <div class="absolute flex h-[13.128px] items-center justify-center left-[calc(91.67%+96.84px)] top-[143.94px] w-[3.451px]">
      <div class="flex-none rotate-180">
        <div class="h-[13.128px] relative w-[3.451px]">
          <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 3.45078 13.1286">
            <g id="Group468-ca">
              <circle cx="1.72537" cy="1.72543" fill="var(--fill-0, #1E1E1E)" id="Ellipse908-ca" r="1.72537" />
              <circle cx="1.72537" cy="6.57306" fill="var(--fill-0, #1E1E1E)" id="Ellipse909-ca" r="1.72537" />
              <circle cx="1.72537" cy="11.4032" fill="var(--fill-0, #1E1E1E)" id="Ellipse910-ca" r="1.72537" />
            </g>
          </svg>
        </div>
      </div>
    </div>

    <!-- Group65: Footer (DECA logo + footer text) -->
    <!-- DECA logo -->
    <div class="-translate-x-1/2 absolute h-[54px] left-[calc(37.5%+34.5px)] top-[1001px] w-[97px]">
      <div class="absolute inset-0 opacity-79 overflow-hidden pointer-events-none">
        <img alt="" class="absolute h-full left-[-13.07%] max-w-none top-0 w-[368.41%]" :src="imgDecaPtHorizontalExtensaPreto1" />
      </div>
    </div>
    <!-- Send5: small send icon in footer -->
    <div class="absolute left-[calc(58.33%+87px)] size-[7px] top-[1034px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 7 7">
        <g :clip-path="'url(#clip0_6_1153-ca)'" id="send-3-ca">
          <path :d="svgPaths.p176b0a00" fill="var(--fill-0, #BBBBBB)" id="VectorSend5-ca" />
          <path :d="svgPaths.p9f4ae70" fill="var(--fill-0, #BBBBBB)" id="VectorSend5b-ca" />
        </g>
        <defs>
          <clipPath id="clip0_6_1153-ca">
            <rect fill="white" height="7" width="7" />
          </clipPath>
        </defs>
      </svg>
    </div>
    <!-- Footer text -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[18px] justify-center leading-[0] left-[calc(54.17%-34px)] not-italic text-[#b7b7b7] text-[9px] text-center top-[1037px] w-[406px]">
      <p>
        <span class="leading-[normal]">Criado por alunos e docentes que valorizam a qualidade, e com princípios de </span>
        <span class="decoration-solid font-['Inter:Bold',sans-serif] font-bold leading-[normal] not-italic underline">Responsible AI</span>
      </p>
    </div>

    <!-- Group72: Chat pill -->
    <!-- Pill background -->
    <div class="-translate-x-1/2 absolute bg-[#faf9f6] h-[78px] left-[calc(50%+0.5px)] rounded-[63px] shadow-[0px_4px_24.2px_2px_rgba(0,0,0,0.21)] top-[883px] w-[967px]" />
    <!-- Placeholder text -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[18px] justify-center leading-[0] left-[calc(25%+70px)] not-italic text-[15px] text-[rgba(0,0,0,0.36)] top-[923px] w-[410px]">
      <p class="leading-[normal]">Faz qualquer pergunta...</p>
    </div>
    <!-- Send button circle (Group19) -->
    <div class="absolute left-[calc(66.67%+82px)] size-[43px] top-[901px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 43.0002 43">
        <g id="Group368-ca">
          <circle cx="21.5002" cy="21.5" fill="var(--fill-0, black)" id="Ellipse656-ca" r="21.5" />
          <g id="search-ca" />
        </g>
      </svg>
    </div>
    <!-- Send2 icon -->
    <div class="absolute h-[16px] left-[calc(66.67%+94px)] top-[915px] w-[19px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 19 16">
        <g :clip-path="'url(#clip0_6_1028-ca)'" id="send-ca">
          <path :d="svgPaths.p16923e00" fill="var(--fill-0, #FAF9F6)" id="VectorSend2-ca" />
        </g>
        <defs>
          <clipPath id="clip0_6_1028-ca">
            <rect fill="white" height="16" width="19" />
          </clipPath>
        </defs>
      </svg>
    </div>
    <!-- Mic icon (Group39) -->
    <div class="absolute h-[69px] left-[calc(25%+21px)] top-[864px] w-[22px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 22 69">
        <g id="Group432-ca">
          <g id="send2-ca" />
          <path :d="svgPaths.p2a2c7c80" fill="var(--fill-0, #1E1E1E)" id="VectorMic-ca" />
        </g>
      </svg>
    </div>

    <!-- Group85: Skeleton rectangles -->
    <div class="absolute h-[667.539px] left-[calc(25%+21px)] top-[139.92px] w-[930px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 930 667.539">
        <g id="Group615-ca">
          <rect fill="var(--fill-0, #E2EDE4)" height="52.732" id="Rectangle247-ca" rx="22" width="532.113" x="360" y="137.085" />
          <rect fill="var(--fill-0, #E2EDE4)" height="52.732" id="Rectangle249-ca" rx="22" width="532.113" x="364.33" y="345.155" />
          <rect fill="var(--fill-0, #E2EDE4)" height="52.732" id="Rectangle251-ca" rx="22" width="532.113" x="397.887" y="551.289" />
          <rect fill="var(--fill-0, #E6E2E2)" height="43.1443" id="Rectangle250-ca" rx="21.5722" width="203.737" x="55.1289" y="411.07" />
          <rect fill="var(--fill-0, #E6E2E2)" height="43.1443" id="Rectangle252-ca" rx="21.5722" width="203.737" x="56.3273" y="606.418" />
          <rect fill="var(--fill-0, #E6E2E2)" height="68.3119" id="Rectangle248-ca" rx="22" width="616.005" x="56.3273" />
          <circle cx="19.7745" cy="433.86" fill="var(--fill-0, #1E1E1E)" id="Ellipse917-ca" r="19.7745" />
          <rect fill="var(--fill-0, #E6E2E2)" height="43.1443" id="Rectangle253-ca" rx="21.5722" width="203.737" x="55.1289" y="243.085" />
          <circle cx="19.7745" cy="265.875" fill="var(--fill-0, #1E1E1E)" id="Ellipse920-ca" r="19.7745" />
          <circle cx="19.7745" cy="43.7436" fill="var(--fill-0, #1E1E1E)" id="Ellipse918-ca" r="19.7745" />
          <circle cx="19.7745" cy="647.764" fill="var(--fill-0, #1E1E1E)" id="Ellipse919-ca" r="19.7745" />
        </g>
      </svg>
    </div>

  </div>
</template>

<script setup lang="ts">
import svgPaths from './svg-88rflhvsvh'
import imgDecaPtHorizontalExtensaPreto1 from './bcf29d3acd99bf822058de8e42a68de0d6a6f843.png'

defineOptions({ name: 'ChatAnexadoFrame' })
</script>
