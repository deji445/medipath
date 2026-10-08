<script setup lang="ts">
import { gsap } from 'gsap';
import { DrawSVGPlugin } from 'gsap/DrawSVGPlugin';
import { MorphSVGPlugin } from 'gsap/MorphSVGPlugin';
import { Observer } from 'gsap/Observer';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { onBeforeUnmount, onMounted, useId, useTemplateRef } from 'vue';

const props = withDefaults(
    defineProps<{
        interactive?: boolean;
        scrollResponsive?: boolean;
    }>(),
    {
        interactive: true,
        scrollResponsive: true,
    },
);

const ribbonRoot = useTemplateRef<HTMLElement>('ribbonRoot');
const gradientId = useId();
let motionContext: gsap.Context | undefined;

onMounted(() => {
    if (!ribbonRoot.value) return;

    gsap.registerPlugin(DrawSVGPlugin, MorphSVGPlugin, Observer, ScrollTrigger);

    motionContext = gsap.context(() => {
        const media = gsap.matchMedia();

        media.add('(prefers-reduced-motion: no-preference)', () => {
            const ribbonPaths = gsap.utils.toArray<SVGPathElement>(
                '[data-ribbon~="draw"]',
            );
            const morphPaths = gsap.utils.toArray<SVGPathElement>(
                '[data-ribbon~="morph"]',
            );
            const motionTarget = ribbonRoot.value?.parentElement;

            gsap.fromTo(
                ribbonPaths,
                { drawSVG: '0% 0%' },
                {
                    drawSVG: '0% 100%',
                    duration: 0.9,
                    stagger: 0.07,
                    ease: 'power2.inOut',
                },
            );

            const ambientRibbon = gsap.timeline({
                paused: true,
                delay: 0.8,
                repeat: -1,
                yoyo: true,
                defaults: { ease: 'sine.inOut' },
            });

            morphPaths.forEach((path) => {
                const destination = path.dataset.morphTo;

                if (!destination) return;

                ambientRibbon.to(
                    path,
                    {
                        morphSVG: {
                            shape: destination,
                            type: 'rotational',
                            map: 'complexity',
                        },
                        duration: 5.8,
                    },
                    0,
                );
            });

            const ribbonSweep = gsap.to('[data-ribbon~="highlight"]', {
                strokeDashoffset: -180,
                duration: 4.8,
                repeat: -1,
                ease: 'none',
                paused: true,
            });

            let ribbonIsVisible = true;
            const syncRibbon = () => {
                const shouldPlay =
                    ribbonIsVisible && document.visibilityState === 'visible';

                if (shouldPlay) {
                    ambientRibbon.play();
                    ribbonSweep.play();
                } else {
                    ambientRibbon.pause();
                    ribbonSweep.pause();
                }
            };

            const visibilityTrigger = ScrollTrigger.create({
                trigger: motionTarget,
                start: 'top bottom',
                end: 'bottom top',
                onEnter: () => {
                    ribbonIsVisible = true;
                    syncRibbon();
                },
                onEnterBack: () => {
                    ribbonIsVisible = true;
                    syncRibbon();
                },
                onLeave: () => {
                    ribbonIsVisible = false;
                    syncRibbon();
                },
                onLeaveBack: () => {
                    ribbonIsVisible = false;
                    syncRibbon();
                },
            });

            if (props.scrollResponsive) {
                gsap.to('[data-ribbon="scroll"]', {
                    yPercent: 14,
                    xPercent: 3,
                    rotation: 1.2,
                    transformOrigin: '50% 50%',
                    ease: 'none',
                    scrollTrigger: {
                        trigger: motionTarget,
                        start: 'top top',
                        end: 'bottom top',
                        scrub: 0.65,
                    },
                });
            }

            const ribbonDrift = ribbonRoot.value?.querySelector<SVGGElement>(
                '[data-ribbon="drift"]',
            );
            const driftX = ribbonDrift
                ? gsap.quickTo(ribbonDrift, 'x', {
                      duration: 0.55,
                      ease: 'power3.out',
                  })
                : undefined;
            const driftY = ribbonDrift
                ? gsap.quickTo(ribbonDrift, 'y', {
                      duration: 0.55,
                      ease: 'power3.out',
                  })
                : undefined;

            const observer = props.interactive
                ? Observer.create({
                      target: motionTarget,
                      type: 'pointer,touch',
                      tolerance: 10,
                      onMove: (self) => {
                          driftX?.(
                              gsap.utils.clamp(-18, 18, self.deltaX * 0.08),
                          );
                          driftY?.(
                              gsap.utils.clamp(-12, 12, self.deltaY * 0.06),
                          );
                      },
                      onStop: () => {
                          driftX?.(0);
                          driftY?.(0);
                      },
                  })
                : undefined;

            document.addEventListener('visibilitychange', syncRibbon);
            syncRibbon();

            return () => {
                observer?.kill();
                visibilityTrigger.kill();
                ambientRibbon.kill();
                ribbonSweep.kill();
                document.removeEventListener('visibilitychange', syncRibbon);
            };
        });

        return () => media.revert();
    }, ribbonRoot.value);
});

onBeforeUnmount(() => motionContext?.revert());
</script>

<template>
    <div ref="ribbonRoot" class="pointer-events-none" aria-hidden="true">
        <svg
            class="h-full w-full"
            viewBox="0 0 1440 720"
            preserveAspectRatio="xMidYMid slice"
            fill="none"
        >
            <defs>
                <linearGradient
                    :id="gradientId"
                    x1="40"
                    y1="620"
                    x2="1450"
                    y2="160"
                    gradientUnits="userSpaceOnUse"
                >
                    <stop offset="0" stop-color="#197f6d" />
                    <stop offset="0.48" stop-color="#2d8f79" />
                    <stop offset="0.78" stop-color="#78aa8f" />
                    <stop offset="1" stop-color="#197f6d" />
                </linearGradient>
            </defs>
            <g data-ribbon="scroll">
                <g data-ribbon="drift">
                    <path
                        data-ribbon="draw morph"
                        data-morph-to="M-120 604C138 548 350 664 582 448C790 254 862 178 1082 254C1270 320 1330 454 1560 270"
                        d="M-120 650C150 474 338 720 566 492C756 302 822 112 1062 206C1254 282 1294 522 1560 326"
                        stroke="#dff2ec"
                        stroke-width="104"
                        stroke-linecap="round"
                        opacity="0.58"
                    />
                    <path
                        data-ribbon="draw morph"
                        data-morph-to="M-120 604C138 548 350 664 582 448C790 254 862 178 1082 254C1270 320 1330 454 1560 270"
                        d="M-120 650C150 474 338 720 566 492C756 302 822 112 1062 206C1254 282 1294 522 1560 326"
                        :stroke="`url(#${gradientId})`"
                        stroke-width="64"
                        stroke-linecap="round"
                        opacity="0.22"
                    />
                    <path
                        data-ribbon="draw morph"
                        data-morph-to="M-120 592C142 570 356 640 592 438C804 256 872 204 1084 270C1262 326 1344 426 1560 254"
                        d="M-120 638C154 496 344 696 576 482C770 304 832 138 1064 222C1246 288 1308 494 1560 310"
                        :stroke="`url(#${gradientId})`"
                        stroke-width="18"
                        stroke-linecap="round"
                        opacity="0.34"
                    />
                    <path
                        data-ribbon="draw morph highlight"
                        data-morph-to="M-120 580C146 592 362 616 602 428C818 258 882 230 1086 286C1254 332 1358 398 1560 238"
                        d="M-120 626C160 518 350 672 586 472C784 306 842 164 1066 238C1238 294 1322 466 1560 294"
                        stroke="#ffffff"
                        stroke-width="3"
                        stroke-linecap="round"
                        stroke-dasharray="72 42"
                        opacity="0.72"
                    />
                </g>
            </g>
        </svg>
    </div>
</template>
