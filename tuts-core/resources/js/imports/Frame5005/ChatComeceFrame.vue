<template>
  <!-- CHAT – Começe um conversa: left-[2283px] top-[249px] w-[1920px] h-[1080px] -->
  <div class="absolute bg-[#faf9f6] h-[1080px] left-[2283px] overflow-clip top-[249px] w-[1920px]">

    <!-- ═══════════════════════════════════════════════════════════════
         Footer / branding (Group58 → Group59 → Send4 + text + DECA)
         ═══════════════════════════════════════════════════════════════ -->
    <!-- Send4 icon -->
    <div class="absolute left-[calc(58.33%+87px)] size-[7px] top-[1031px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 7 7">
        <g :clip-path="'url(#cc-clip0_6_1153)'" id="send-3-cc">
          <path :d="svgPaths.p176b0a00" fill="var(--fill-0, #BBBBBB)" id="Vector" />
          <path :d="svgPaths.p9f4ae70" fill="var(--fill-0, #BBBBBB)" id="Vector_2" />
        </g>
        <defs>
          <clipPath id="cc-clip0_6_1153">
            <rect fill="white" height="7" width="7" />
          </clipPath>
        </defs>
      </svg>
    </div>
    <!-- Footer text -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[18px] justify-center leading-[0] left-[calc(54.17%-34px)] not-italic text-[#b7b7b7] text-[9px] text-center top-[1034px] w-[406px]">
      <p>
        <span class="leading-[normal]">Criado por alunos e docentes que valorizam a qualidade, e com princípios de </span>
        <span class="decoration-solid font-['Inter:Bold',sans-serif] font-bold leading-[normal] not-italic underline">Responsible AI</span>
      </p>
    </div>
    <!-- DECA logo -->
    <div class="-translate-x-1/2 absolute h-[54px] left-[calc(37.5%+34.5px)] top-[998px] w-[97px]">
      <div class="absolute inset-0 opacity-79 overflow-hidden pointer-events-none">
        <img alt="" class="absolute h-full left-[-13.07%] max-w-none top-0 w-[368.41%]" :src="imgDecaPtHorizontalExtensaPreto1" />
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         Chat pill / input bar (Group71 → Group26+Group34 inlined)
         ═══════════════════════════════════════════════════════════════ -->
    <!-- Pill background -->
    <div class="-translate-x-1/2 absolute bg-[#faf9f6] h-[78px] left-[calc(50%+0.5px)] rounded-[63px] shadow-[0px_4px_24.2px_2px_rgba(0,0,0,0.21)] top-[919px] w-[967px]" />
    <!-- Placeholder text -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[18px] justify-center leading-[0] left-[calc(25%+70px)] not-italic text-[15px] text-[rgba(0,0,0,0.36)] top-[959px] w-[410px]">
      <p class="leading-[normal]">Faz qualquer pergunta...</p>
    </div>
    <!-- Send button circle (Group26 → Group9 → Group33 → Group4 + Send1) -->
    <div class="absolute left-[calc(66.67%+82px)] size-[43px] top-[937px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 43.0001 43">
        <circle cx="21.5" cy="21.5" fill="var(--fill-0, black)" id="Ellipse 656cc" r="21.5" />
      </svg>
    </div>
    <div class="absolute h-[16px] left-[calc(66.67%+94px)] top-[951px] w-[19px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 19 16">
        <g :clip-path="'url(#cc-clip0_6_1028)'" id="send-cc">
          <path :d="svgPaths.p16923e00" fill="var(--fill-0, #FAF9F6)" id="Vector" />
        </g>
        <defs>
          <clipPath id="cc-clip0_6_1028">
            <rect fill="white" height="16" width="19" />
          </clipPath>
        </defs>
      </svg>
    </div>
    <!-- Mic/attach icon (Group34 → Group10 → Group35) -->
    <div class="absolute h-[69px] left-[calc(25%+21px)] top-[900px] w-[22px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 22 69">
        <g id="Group 432cc">
          <path :d="svgPaths.p2a2c7c80" fill="var(--fill-0, #1E1E1E)" id="Vector" />
        </g>
      </svg>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         Sidebar (left-0 top-[-28px] w-[76px] h-[1080px])
         ═══════════════════════════════════════════════════════════════ -->
    <div class="absolute h-[1080px] left-0 top-[-28px] w-[76px]">
      <div class="absolute bg-[#1e1e1e] inset-[0_0_-14.17%_0]" />
      <!-- Group48 → Group46 → Group11 + Calendar1 + FolderAdd1 (FAF9F6 fill) -->
      <div class="absolute inset-[16.76%_31.58%_80.74%_32.89%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27 27">
          <g id="Group 372cc">
            <path :d="svgPaths.pfb9b730" fill="var(--fill-0, #FAF9F6)" id="Vector" />
          </g>
        </svg>
      </div>
      <div class="absolute inset-[23.52%_31.58%_74.07%_32.89%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27 26">
          <g id="calendar-cc">
            <g :clip-path="'url(#cc-clip0_6_1012)'">
              <path :d="svgPaths.p26b4fe00" fill="var(--fill-0, #FAF9F6)" id="Vector" />
              <path :d="svgPaths.p2e434e00" fill="var(--fill-0, #FAF9F6)" id="Vector_2" />
              <path :d="svgPaths.pbd5a100" fill="var(--fill-0, #FAF9F6)" id="Vector_3" />
              <path :d="svgPaths.p7923600" fill="var(--fill-0, #FAF9F6)" id="Vector_4" />
              <path :d="svgPaths.peff05f1" fill="var(--fill-0, #FAF9F6)" id="Vector_5" />
              <path :d="svgPaths.p25bf2b00" fill="var(--fill-0, #FAF9F6)" id="Vector_6" />
            </g>
          </g>
          <defs>
            <clipPath id="cc-clip0_6_1012">
              <path d="M0 0H27V26H0V0Z" fill="white" />
            </clipPath>
          </defs>
        </svg>
      </div>
      <div class="absolute inset-[30.65%_32.89%_67.04%_31.58%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27 25">
          <g id="folder-add-cc">
            <g :clip-path="'url(#cc-clip0_6_1045)'">
              <path :d="svgPaths.p73efb00" fill="var(--fill-0, #FAF9F6)" id="Vector" />
              <path :d="svgPaths.p1c6b8300" fill="var(--fill-0, #FAF9F6)" id="Vector_2" />
            </g>
          </g>
          <defs>
            <clipPath id="cc-clip0_6_1045">
              <path d="M0 0H27V25H0V0Z" fill="white" />
            </clipPath>
          </defs>
        </svg>
      </div>
      <!-- Group75: Tuts logo (white fill) -->
      <div class="absolute inset-[37.96%_27.63%_59.36%_36.84%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 27.0001 28.8687">
          <g id="Group 614cc">
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
      <!-- Logout1: empty positioned div -->
      <div class="absolute inset-[74.26%_-1130.26%_11.3%_1046.05%]" />
      <!-- Group141: profile icon (white fill) -->
      <div class="absolute inset-[91.85%_25.35%_5.19%_36.84%]">
        <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 28.7347 32">
          <g id="Group 683cc">
            <path :d="svgPaths.p20771200" fill="var(--fill-0, white)" id="Vector" />
            <path :d="svgPaths.p19644800" fill="var(--fill-0, white)" id="Vector_2" />
          </g>
        </svg>
      </div>
    </div><!-- /Sidebar -->

    <!-- ═══════════════════════════════════════════════════════════════
         Nav bar — first pass
         ═══════════════════════════════════════════════════════════════ -->
    <!-- "Responsible AI" -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Medium',sans-serif] font-medium h-[31px] justify-center leading-[0] left-[calc(87.5%-55.5px)] not-italic text-[11px] text-black text-center top-[62.5px] tracking-[0.33px] w-[121px]">
      <p class="leading-[normal]">Responsible AI</p>
    </div>
    <!-- Info1 icon (rotated) -->
    <div class="absolute flex h-[17px] items-center justify-center left-[calc(75%+116px)] top-[55px] w-[17.45px]">
      <div class="-scale-y-100 flex-none rotate-180">
        <div class="h-[17px] relative w-[17.45px]">
          <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 17.4497 17">
            <g id="Info1-cc">
              <path :d="svgPaths.p5490e32" fill="var(--fill-0, black)" id="Vector" />
              <path :d="svgPaths.p2c6cab00" fill="var(--fill-0, black)" id="Vector_2" />
              <path :d="svgPaths.p2205ec80" fill="var(--fill-0, black)" id="Vector_3" />
            </g>
          </svg>
        </div>
      </div>
    </div>
    <!-- Group12 → Group13: toggle switch -->
    <div class="absolute h-[23px] left-[calc(75%+48px)] top-[52px] w-[55px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 55 23">
        <g id="Group 369cc">
          <rect fill="var(--fill-0, white)" height="22" rx="11" stroke="var(--stroke-0, #54A64E)" width="54" x="0.5" y="0.5" />
          <circle cx="11.5" cy="11.5" fill="var(--fill-0, #54A64E)" r="8" stroke="var(--stroke-0, #54A64E)" />
          <path :d="svgPaths.p2df65710" fill="var(--fill-0, #A7C68E)" id="tick" stroke="var(--stroke-0, #54A64E)" stroke-width="0.656338" />
        </g>
      </svg>
    </div>
    <!-- Sunny1 -->
    <div class="absolute h-[16px] left-[calc(75%+20px)] top-[56px] w-[17px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 17 16">
        <g :clip-path="'url(#cc-clip0_6_1102)'" id="sunny-cc">
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
          <clipPath id="cc-clip0_6_1102">
            <rect fill="white" height="16" width="17" />
          </clipPath>
        </defs>
      </svg>
    </div>
    <!-- Avatar circle -->
    <div class="absolute left-[calc(91.67%-27px)] size-[43px] top-[37px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 43 43">
        <circle cx="21.5" cy="21.5" fill="var(--fill-0, #DF32AB)" id="Ellipse 871cc" r="21.5" />
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
    <!-- Bell1 -->
    <div class="absolute h-[26px] left-[calc(83.33%+97px)] top-[46px] w-[25px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 25 26">
        <g :clip-path="'url(#cc-clip0_6_1093)'" id="bell-2-cc">
          <path :d="svgPaths.p20627470" fill="var(--fill-0, black)" id="Vector" />
        </g>
        <defs>
          <clipPath id="cc-clip0_6_1093">
            <rect fill="white" height="26" width="25" />
          </clipPath>
        </defs>
      </svg>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         Hero text
         ═══════════════════════════════════════════════════════════════ -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(41.67%+7px)] not-italic text-[32px] text-black top-[850px] tracking-[-0.32px] w-[301px]">
      <p class="leading-[40px]">Estudar com o Tut's</p>
    </div>
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal justify-center leading-[0] left-[calc(50%-151px)] not-italic text-[#676767] text-[14px] top-[880px] tracking-[-0.14px] whitespace-nowrap">
      <p class="leading-[40px]">Consulta os materiais disponíveis para estudar </p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         3 choice cards (Group82 → Group89 + Group90 + Group91)
         ═══════════════════════════════════════════════════════════════ -->

    <!-- ─── Card 1: discipline (Group89) ────────────────────────────── -->
    <!-- Card gradient background -->
    <div
      class="absolute left-[calc(25%+42px)] rounded-[22px] shadow-[0px_3px_14.7px_-3px_rgba(0,0,0,0.25)] size-[250px] top-[512px]"
      :style="{ backgroundImage: 'linear-gradient(90deg, rgb(255, 255, 255) 0%, rgb(255, 255, 255) 100%), linear-gradient(90deg, rgba(0, 0, 0, 0.2) 0%, rgba(0, 0, 0, 0.2) 100%), linear-gradient(90deg, rgba(0, 0, 0, 0.01) 0%, rgba(0, 0, 0, 0.01) 100%), linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0) 100%), linear-gradient(rgb(108, 178, 149) 0%, rgb(84, 166, 78) 100%)' }"
    />
    <!-- Card 1 title -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold h-[44px] justify-center leading-[0] left-[calc(33.33%-101px)] not-italic text-[#54a64e] text-[15px] top-[604px] tracking-[-0.45px] w-[208px]">
      <p class="leading-[normal]">Escolhe ou menciona disciplina</p>
    </div>
    <!-- Card 1 separator line -->
    <div class="absolute h-0 left-[calc(25%+59px)] top-[626px] w-[216px]">
      <div class="absolute inset-[-1px_0_0_0]">
        <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 216 1">
          <line id="Line 66cc" stroke="var(--stroke-0, #1E1E1E)" stroke-opacity="0.06" x2="216" y1="0.5" y2="0.5" />
        </svg>
      </div>
    </div>
    <!-- Group76: Tuts logo icon (card 1) -->
    <div class="absolute inset-[49.63%_70.02%_46.85%_28.12%]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 35.5408 38">
        <g id="Group 615cc1">
          <path :d="svgPaths.p1961d400" fill="var(--fill-0, #A7C68E)" id="Vector" />
          <path :d="svgPaths.p14411900" fill="var(--fill-0, #009957)" id="Vector_2" />
          <path :d="svgPaths.p2498ee80" fill="var(--fill-0, #6CB295)" id="Vector_3" />
          <path :d="svgPaths.p3682a880" fill="var(--fill-0, #6CB295)" id="Vector_4" />
          <path :d="svgPaths.pc002e70" fill="var(--fill-0, #54A64E)" id="Vector_5" />
          <path :d="svgPaths.p2c2dfa00" fill="var(--fill-0, #6CB295)" id="Vector_6" />
          <path :d="svgPaths.p1f195b00" fill="var(--fill-0, #54A64E)" id="Vector_7" />
        </g>
      </svg>
    </div>
    <!-- Group83 → Group36 + Group77: discipline tags -->
    <!-- @disciplina tag (Group36) -->
    <div class="absolute bg-[#cfe9ce] h-[25.947px] left-[calc(25%+59px)] rounded-[10px] top-[677.05px] w-[102px]" />
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[16.105px] justify-center leading-[0] left-[calc(29.17%+30px)] not-italic text-[10px] text-black text-center top-[690.47px] w-[62.632px]">
      <p class="leading-[normal]">@disciplina</p>
    </div>
    <!-- @Tacs tag (Group77) -->
    <div class="absolute bg-[#cfe9ce] h-[25.947px] left-[calc(25%+60px)] rounded-[10px] top-[642px] w-[102px]" />
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[16.105px] justify-center leading-[0] left-[calc(29.17%+31px)] not-italic text-[10px] text-black text-center top-[655.42px] w-[62.632px]">
      <p class="leading-[normal]">@Tacs</p>
    </div>
    <!-- "Ver disciplinas" link -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[16px] justify-center leading-[0] left-[calc(37.5%-12.5px)] not-italic text-[14px] text-black text-center top-[737px] w-[99px]">
      <p class="decoration-solid leading-[normal] underline">Ver disciplinas</p>
    </div>

    <!-- ─── Card 2: space (Group90) ──────────────────────────────────── -->
    <div
      class="-translate-x-1/2 absolute left-1/2 rounded-[22px] shadow-[0px_3px_14.7px_-3px_rgba(0,0,0,0.25)] size-[250px] top-[512px]"
      :style="{ backgroundImage: 'linear-gradient(90deg, rgb(255, 255, 255) 0%, rgb(255, 255, 255) 100%), linear-gradient(90deg, rgba(0, 0, 0, 0.2) 0%, rgba(0, 0, 0, 0.2) 100%), linear-gradient(90deg, rgba(0, 0, 0, 0.01) 0%, rgba(0, 0, 0, 0.01) 100%), linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0) 100%), linear-gradient(rgb(108, 178, 149) 0%, rgb(84, 166, 78) 100%)' }"
    />
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold h-[44px] justify-center leading-[0] left-[calc(50%-108px)] not-italic text-[#54a64e] text-[15px] top-[604px] tracking-[-0.45px] w-[208px]">
      <p class="leading-[normal]">Escolhe ou cria um novo espaço</p>
    </div>
    <div class="absolute h-0 left-[calc(41.67%+52px)] top-[626px] w-[216px]">
      <div class="absolute inset-[-1px_0_0_0]">
        <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 216 1">
          <line id="Line 68cc" stroke="var(--stroke-0, #1E1E1E)" stroke-opacity="0.06" x2="216" y1="0.5" y2="0.5" />
        </svg>
      </div>
    </div>
    <!-- Group87: Tuts logo icon (card 2) -->
    <div class="absolute inset-[49.63%_53.72%_46.85%_44.43%]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 35.5408 38">
        <g id="Group 615cc2">
          <path :d="svgPaths.p1961d400" fill="var(--fill-0, #A7C68E)" id="Vector" />
          <path :d="svgPaths.p14411900" fill="var(--fill-0, #009957)" id="Vector_2" />
          <path :d="svgPaths.p2498ee80" fill="var(--fill-0, #6CB295)" id="Vector_3" />
          <path :d="svgPaths.p3682a880" fill="var(--fill-0, #6CB295)" id="Vector_4" />
          <path :d="svgPaths.pc002e70" fill="var(--fill-0, #54A64E)" id="Vector_5" />
          <path :d="svgPaths.p2c2dfa00" fill="var(--fill-0, #6CB295)" id="Vector_6" />
          <path :d="svgPaths.p1f195b00" fill="var(--fill-0, #54A64E)" id="Vector_7" />
        </g>
      </svg>
    </div>
    <!-- "Ver espaços" link -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Regular',sans-serif] font-normal h-[16px] justify-center leading-[0] left-[calc(54.17%-12.5px)] not-italic text-[14px] text-black text-center top-[737px] w-[99px]">
      <p class="decoration-solid leading-[normal] underline">Ver espaços</p>
    </div>

    <!-- ─── Card 3: temporary (Group91) ─────────────────────────────── -->
    <div class="absolute border border-black border-dashed left-[calc(58.33%+29px)] rounded-[22px] shadow-[0px_3px_14.7px_-3px_rgba(0,0,0,0.25)] size-[250px] top-[512px]" />
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold h-[44px] justify-center leading-[0] left-[calc(66.67%-114px)] not-italic text-[#54a64e] text-[15px] top-[604px] tracking-[-0.45px] w-[208px]">
      <p class="leading-[normal]">Começe um conversa temporária</p>
    </div>
    <!-- Dashed separator line -->
    <div class="absolute h-0 left-[calc(58.33%+46px)] top-[626px] w-[216px]">
      <div class="absolute inset-[-1px_0_0_0]">
        <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 216 1">
          <line id="Line 68cc3" stroke="var(--stroke-0, #1E1E1E)" stroke-dasharray="12 12" stroke-opacity="0.06" x2="216" y1="0.5" y2="0.5" />
        </svg>
      </div>
    </div>
    <!-- Group88: 7 vector images -->
    <div class="absolute inset-[51.26%_38.23%_46.87%_60.78%]">
      <img alt="" class="absolute block inset-0 max-w-none size-full" height="20.242" :src="imgVector" width="18.9" />
    </div>
    <div class="absolute inset-[49.63%_38.3%_48.77%_60.78%]">
      <img alt="" class="absolute block inset-0 max-w-none size-full" height="17.247" :src="imgVector1" width="17.612" />
    </div>
    <div class="absolute inset-[49.63%_37.37%_48.74%_61.72%]">
      <img alt="" class="absolute block inset-0 max-w-none size-full" height="17.589" :src="imgVector2" width="17.477" />
    </div>
    <div class="absolute inset-[51.61%_37.37%_46.85%_61.79%]">
      <img alt="" class="absolute block inset-0 max-w-none size-full" height="16.659" :src="imgVector3" width="16.153" />
    </div>
    <div class="absolute inset-[51.3%_37.38%_47.53%_61.78%]">
      <img alt="" class="absolute block inset-0 max-w-none size-full" height="12.568" :src="imgVector4" width="16.199" />
    </div>
    <div class="absolute inset-[51.26%_38.23%_47.55%_61.16%]">
      <img alt="" class="absolute block inset-0 max-w-none size-full" height="12.863" :src="imgVector5" width="11.647" />
    </div>
    <div class="absolute inset-[50.28%_38.3%_48.77%_61.16%]">
      <img alt="" class="absolute block inset-0 max-w-none size-full" height="10.17" :src="imgVector6" width="10.372" />
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         Nav bar — duplicate pass (Figma export artifact, same positions)
         ═══════════════════════════════════════════════════════════════ -->
    <!-- Avatar circle (dup) -->
    <div class="absolute left-[calc(91.67%-27px)] size-[43px] top-[37px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 43 43">
        <circle cx="21.5" cy="21.5" fill="var(--fill-0, #DF32AB)" id="Ellipse 871cc2" r="21.5" />
      </svg>
    </div>
    <!-- "M" (dup) -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(91.67%-5px)] not-italic size-[28px] text-[24px] text-center text-white top-[58px]">
      <p class="leading-[normal]">M</p>
    </div>
    <!-- "MARIA FRIAS" (dup) -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Bold',sans-serif] font-bold justify-center leading-[0] left-[calc(91.67%+68.5px)] not-italic text-[13px] text-black text-center top-[54px] whitespace-nowrap">
      <p class="leading-[normal]">MARIA FRIAS</p>
    </div>
    <!-- "Responsible AI" (dup) -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Medium',sans-serif] font-medium h-[31px] justify-center leading-[0] left-[calc(87.5%-55.5px)] not-italic text-[11px] text-black text-center top-[62.5px] tracking-[0.33px] w-[121px]">
      <p class="leading-[normal]">Responsible AI</p>
    </div>
    <!-- Group93 → Group14: toggle (dup) -->
    <div class="absolute h-[23px] left-[calc(75%+48px)] top-[52px] w-[55px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 55 23">
        <g id="Group 369cc2">
          <rect fill="var(--fill-0, white)" height="22" rx="11" stroke="var(--stroke-0, #54A64E)" width="54" x="0.5" y="0.5" />
          <circle cx="11.5" cy="11.5" fill="var(--fill-0, #54A64E)" r="8" stroke="var(--stroke-0, #54A64E)" />
          <path :d="svgPaths.p2df65710" fill="var(--fill-0, #A7C68E)" stroke="var(--stroke-0, #54A64E)" stroke-width="0.656338" />
        </g>
      </svg>
    </div>
    <!-- Info2 icon (dup, same paths as Info1) -->
    <div class="absolute flex h-[17px] items-center justify-center left-[calc(75%+116px)] top-[55px] w-[17.45px]">
      <div class="-scale-y-100 flex-none rotate-180">
        <div class="h-[17px] relative w-[17.45px]">
          <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 17.4497 17">
            <g id="Info2-cc">
              <path :d="svgPaths.p5490e32" fill="var(--fill-0, black)" id="Vector" />
              <path :d="svgPaths.p2c6cab00" fill="var(--fill-0, black)" id="Vector_2" />
              <path :d="svgPaths.p2205ec80" fill="var(--fill-0, black)" id="Vector_3" />
            </g>
          </svg>
        </div>
      </div>
    </div>
    <!-- Sunny2 (dup) -->
    <div class="absolute h-[16px] left-[calc(75%+20px)] top-[56px] w-[17px]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 17 16">
        <g :clip-path="'url(#cc-clip0_6_1102b)'" id="sunny-cc2">
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
          <clipPath id="cc-clip0_6_1102b">
            <rect fill="white" height="16" width="17" />
          </clipPath>
        </defs>
      </svg>
    </div>
    <!-- "Estudante" (dup) -->
    <div class="-translate-x-1/2 -translate-y-1/2 absolute flex flex-col font-['Inter:Italic',sans-serif] font-normal italic justify-center leading-[0] left-[calc(91.67%+50px)] text-[#bdbaba] text-[10px] text-center top-[68px] whitespace-nowrap">
      <p class="leading-[normal]">Estudante</p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         Tab bar (Group92 inlined + "UC'S/Espaços" + dividers + logo)
         ═══════════════════════════════════════════════════════════════ -->
    <!-- Group92: "Conversa" tab text + Group78 chat icon -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Medium',sans-serif] font-medium justify-center leading-[0] left-[calc(29.17%-17px)] not-italic text-[#1e1e1e] text-[14px] top-[94px] tracking-[-0.14px] whitespace-nowrap">
      <p class="leading-[40px]">Conversa</p>
    </div>
    <!-- Group78: chat icon -->
    <div class="absolute inset-[7.96%_72.36%_90.38%_26.77%]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 16.7108 17.8682">
        <g id="Group 614cc2">
          <path :d="svgPaths.p2918d980" fill="var(--fill-0, #1E1E1E)" id="Vector" />
          <path :d="svgPaths.p39604500" fill="var(--fill-0, #1E1E1E)" id="Vector_2" />
          <path :d="svgPaths.p216ccb20" fill="var(--fill-0, #1E1E1E)" id="Vector_3" />
          <path :d="svgPaths.p26b7bd80" fill="var(--fill-0, #1E1E1E)" id="Vector_4" />
          <path :d="svgPaths.p34966c0" fill="var(--fill-0, #1E1E1E)" id="Vector_5" />
          <path :d="svgPaths.pfd5d400" fill="var(--fill-0, #1E1E1E)" id="Vector_6" />
          <path :d="svgPaths.p1086d200" fill="var(--fill-0, #1E1E1E)" id="Vector_7" />
        </g>
      </svg>
    </div>
    <!-- "UC'S/Espaços" tab text -->
    <div class="-translate-y-1/2 absolute flex flex-col font-['Inter:Medium',sans-serif] font-medium justify-center leading-[0] left-[calc(37.5%-50px)] not-italic text-[#1e1e1e] text-[14px] top-[94px] tracking-[-0.14px] whitespace-nowrap">
      <p class="leading-[40px]">UC'S/Espaços</p>
    </div>
    <!-- Gradient divider line -->
    <div class="absolute h-0 left-[147px] top-[114px] w-[1741px]">
      <div class="absolute inset-[-1px_0_0_0]">
        <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 1741 1">
          <line id="Line 68cc4" stroke="url(#paint0_linear_6_987)" x2="1741" y1="0.5" y2="0.5" />
          <defs>
            <linearGradient gradient-units="userSpaceOnUse" id="paint0_linear_6_987" x1="0" x2="1741" y1="1.5" y2="1.5">
              <stop offset="0.182692" stop-color="#6CB295" />
              <stop offset="0.514423" stop-color="#1E1E1E" />
              <stop offset="0.822115" stop-color="#6CB295" />
            </linearGradient>
          </defs>
        </svg>
      </div>
    </div>
    <!-- Active tab underline -->
    <div class="absolute h-0 left-[calc(25%+24px)] top-[115px] w-[125px]">
      <div class="absolute inset-[-2.5px_0_0_0]">
        <svg class="block size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 125 2.5">
          <line id="Line 69cc" stroke="var(--stroke-0, black)" stroke-width="2.5" x2="125" y1="1.25" y2="1.25" />
        </svg>
      </div>
    </div>
    <!-- Tuts logo top-left corner -->
    <div class="absolute inset-[0_98.7%_97.6%_0]">
      <svg class="absolute block inset-0 size-full" fill="none" preserve-aspect-ratio="none" viewBox="0 0 24.8933 25.8777">
        <path :d="svgPaths.pffcd80" fill="var(--fill-0, black)" id="Vector" />
      </svg>
    </div>

  </div><!-- /ChatComeceUmConversa -->
</template>

<script setup lang="ts">
import svgPaths from './svg-88rflhvsvh'
import imgDecaPtHorizontalExtensaPreto1 from './bcf29d3acd99bf822058de8e42a68de0d6a6f843.png'
import imgVector from './d726999a8de3f141ce8ec6cfe94a8613c3bed50a.png'
import imgVector1 from './d43aaf7ea75f15854c0d1318a9a54a69c1756422.png'
import imgVector2 from './7f799e63393f594d88cb012924dc7131eb55c6a1.png'
import imgVector3 from './458e24c55184e2cca6389e06c8291eb737d954eb.png'
import imgVector4 from './b33665977920f84d3ab66d05b986b9bb21c6f802.png'
import imgVector5 from './08b064c8d6db13dbc1182df2e5630c7921e73894.png'
import imgVector6 from './db0f2b1b64edc6da34ae1056a88ea2f0527575f4.png'

defineOptions({ name: 'ChatComeceFrame' })
</script>
