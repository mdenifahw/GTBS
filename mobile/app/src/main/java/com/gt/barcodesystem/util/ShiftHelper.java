package com.gt.barcodesystem.util;

import java.util.Calendar;
import java.util.TimeZone;

public class ShiftHelper {

    public static int getCurrentShift() {
        Calendar calendar = Calendar.getInstance(TimeZone.getTimeZone("Asia/Jakarta"));
        int hour = calendar.get(Calendar.HOUR_OF_DAY);

        if (hour >= 7 && hour < 15) {
            return 1;
        } else if (hour >= 15 && hour < 22) {
            return 2;
        } else {
            return 3;
        }
    }

    public static String getShiftLabel(int shift) {
        switch (shift) {
            case 1:
                return "Shift 1 (07:00 - 15:00)";
            case 2:
                return "Shift 2 (15:00 - 22:00)";
            case 3:
                return "Shift 3 (22:00 - 07:00)";
            default:
                return "Shift " + shift;
        }
    }
}
