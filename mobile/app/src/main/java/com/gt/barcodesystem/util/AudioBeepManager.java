package com.gt.barcodesystem.util;

import android.media.AudioManager;
import android.media.ToneGenerator;
import android.util.Log;

import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

public class AudioBeepManager {

    private static final String TAG = "AudioBeepManager";
    private static ToneGenerator toneGenerator;
    private static final ExecutorService soundExecutor = Executors.newSingleThreadExecutor();

    private static ToneGenerator getToneGenerator() {
        if (toneGenerator == null) {
            try {
                toneGenerator = new ToneGenerator(AudioManager.STREAM_MUSIC, 80);
            } catch (Exception e) {
                Log.e(TAG, "Audio ToneGenerator not supported: " + e.getMessage());
            }
        }
        return toneGenerator;
    }

    public static void playSuccessBeep() {
        soundExecutor.execute(() -> {
            try {
                ToneGenerator tg = getToneGenerator();
                if (tg != null) {
                    tg.startTone(ToneGenerator.TONE_PROP_BEEP, 150);
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to play success beep: " + e.getMessage());
            }
        });
    }

    public static void playErrorBeep() {
        soundExecutor.execute(() -> {
            try {
                ToneGenerator tg = getToneGenerator();
                if (tg != null) {
                    tg.startTone(ToneGenerator.TONE_SUP_ERROR, 300);
                }
            } catch (Exception e) {
                Log.e(TAG, "Failed to play error beep: " + e.getMessage());
            }
        });
    }
}
